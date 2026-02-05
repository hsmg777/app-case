<?php

namespace App\Repositories\Reporting;

use App\Models\Sales\Sale;
use App\Models\Sales\SalePayment;
use App\Models\Sri\ElectronicInvoice;
use App\Models\Cashier\CashSession;
use App\Models\Inventory\Inventory;
use App\Models\Product\Product;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportingRepository
{
    public function getInvoiceStatuses(string $estado, string $q, int $maxReviewHours): LengthAwarePaginator
    {
        $query = ElectronicInvoice::with('sale')
            ->orderByDesc('updated_at');

        if ($estado !== '') {
            if ($estado === 'PENDIENTE_REVISION') {
                $query->whereNotIn('estado_sri', ['AUTORIZADO', 'RECHAZADO'])
                    ->where('updated_at', '<=', now()->subHours($maxReviewHours));
            } else {
                $query->where('estado_sri', $estado);
            }
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('clave_acceso', 'like', "%{$q}%")
                    ->orWhereHas('sale', function ($saleQ) use ($q) {
                        $saleQ->where('id', $q)
                            ->orWhere('num_factura', 'like', "%{$q}%");
                    });
            });
        }

        return $query->paginate(50)->withQueryString();
    }

    public function getDailySalesByPaymentMethod(string $fechaStr, ?int $bodegaId): Collection
    {
        $query = SalePayment::query()
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->leftJoin('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->whereDate('sales.fecha_venta', $fechaStr);

        if ($bodegaId) {
            $query->where('sales.bodega_id', $bodegaId);
        }

        return $query
            ->selectRaw('COALESCE(payment_methods.nombre, sale_payments.metodo) as metodo')
            ->selectRaw('COUNT(sale_payments.id) as pagos')
            ->selectRaw('COUNT(DISTINCT sales.id) as ventas')
            ->selectRaw('SUM(sale_payments.monto) as total_monto')
            ->groupByRaw('COALESCE(payment_methods.nombre, sale_payments.metodo)')
            ->orderByDesc('total_monto')
            ->get();
    }

    public function getTotalVentasByDate(string $fechaStr, ?int $bodegaId): float
    {
        $query = Sale::whereDate('fecha_venta', $fechaStr);

        if ($bodegaId) {
            $query->where('bodega_id', $bodegaId);
        }

        return (float) $query->sum('total');
    }

    public function getTotalsByBodega(string $fechaStr): Collection
    {
        return Sale::query()
            ->leftJoin('bodegas', 'sales.bodega_id', '=', 'bodegas.id')
            ->whereDate('sales.fecha_venta', $fechaStr)
            ->selectRaw('sales.bodega_id as bodega_id')
            ->selectRaw('COALESCE(bodegas.nombre, \'Sin bodega\') as bodega_nombre')
            ->selectRaw('COUNT(sales.id) as ventas')
            ->selectRaw('SUM(sales.total) as total_facturado')
            ->groupByRaw('sales.bodega_id, COALESCE(bodegas.nombre, \'Sin bodega\')')
            ->orderByDesc('total_facturado')
            ->get();
    }

    public function getClosedCashSessionsByDate(string $fechaStr): Collection
    {
        return CashSession::with(['opener', 'closer'])
            ->whereNotNull('closed_at')
            ->whereDate('closed_at', $fechaStr)
            ->orderBy('closed_at', 'asc')
            ->get();
    }

    public function getPaymentTotalsForUserBetween(int $userId, Carbon $from, Carbon $to): Collection
    {
        return SalePayment::query()
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->leftJoin('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('sales.user_id', $userId)
            ->whereBetween('sales.fecha_venta', [$from, $to])
            ->selectRaw('COALESCE(payment_methods.nombre, sale_payments.metodo) as metodo')
            ->selectRaw('COUNT(sale_payments.id) as pagos')
            ->selectRaw('COUNT(DISTINCT sales.id) as ventas')
            ->selectRaw('SUM(sale_payments.monto) as total_monto')
            ->groupByRaw('COALESCE(payment_methods.nombre, sale_payments.metodo)')
            ->orderByDesc('total_monto')
            ->get();
    }

    public function getPaymentMethodsBetween(Carbon $from, Carbon $to): Collection
    {
        return SalePayment::query()
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->leftJoin('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->whereBetween('sales.fecha_venta', [$from, $to])
            ->selectRaw('COALESCE(payment_methods.nombre, sale_payments.metodo) as metodo')
            ->distinct()
            ->orderBy('metodo')
            ->pluck('metodo');
    }

    public function getMonthlySalesSummary(Carbon $from, Carbon $to): Collection
    {
        return Sale::query()
            ->join('sale_items as si', 'si.sale_id', '=', 'sales.id')
            ->join('products as p', 'p.id', '=', 'si.producto_id')
            ->whereBetween('sales.fecha_venta', [$from, $to])
            ->where('sales.tipo_documento', 'FACTURA')
            ->selectRaw("DATE_TRUNC('month', sales.fecha_venta) as mes")
            ->selectRaw('COUNT(DISTINCT sales.id) as comprobantes')
            ->selectRaw('SUM(CASE WHEN COALESCE(p.iva_porcentaje, 0) > 0 THEN si.total ELSE 0 END) as sub15')
            ->selectRaw('SUM(CASE WHEN COALESCE(p.iva_porcentaje, 0) = 0 THEN si.total ELSE 0 END) as sub0')
            ->selectRaw('SUM(sales.iva) as iva')
            ->selectRaw('SUM(sales.total) as total')
            ->groupByRaw("DATE_TRUNC('month', sales.fecha_venta)")
            ->orderByRaw("DATE_TRUNC('month', sales.fecha_venta) ASC")
            ->get();
    }

    public function getSalesMinMaxDates(): ?array
    {
        $row = Sale::query()
            ->where('tipo_documento', 'FACTURA')
            ->selectRaw('MIN(fecha_venta) as min_fecha')
            ->selectRaw('MAX(fecha_venta) as max_fecha')
            ->first();

        if (!$row || !$row->min_fecha || !$row->max_fecha) {
            return null;
        }

        return [
            'min' => Carbon::parse($row->min_fecha),
            'max' => Carbon::parse($row->max_fecha),
        ];
    }

    public function getSalesSummaryByRange(Carbon $from, Carbon $to): ?object
    {
        return Sale::query()
            ->join('sale_items as si', 'si.sale_id', '=', 'sales.id')
            ->join('products as p', 'p.id', '=', 'si.producto_id')
            ->whereBetween('sales.fecha_venta', [$from, $to])
            ->where('sales.tipo_documento', 'FACTURA')
            ->selectRaw('COUNT(DISTINCT sales.id) as comprobantes')
            ->selectRaw('SUM(CASE WHEN COALESCE(p.iva_porcentaje, 0) > 0 THEN si.total ELSE 0 END) as sub15')
            ->selectRaw('SUM(CASE WHEN COALESCE(p.iva_porcentaje, 0) = 0 THEN si.total ELSE 0 END) as sub0')
            ->selectRaw('SUM(sales.iva) as iva')
            ->selectRaw('SUM(sales.total) as total')
            ->first();
    }

    public function getSalesFrequencyByRange(Carbon $from, Carbon $to): Collection
    {
        return Sale::query()
            ->whereBetween('fecha_venta', [$from, $to])
            ->where('tipo_documento', 'FACTURA')
            ->selectRaw("DATE(fecha_venta) as dia")
            ->selectRaw('COUNT(sales.id) as comprobantes')
            ->selectRaw('SUM(sales.total) as total')
            ->groupByRaw("DATE(fecha_venta)")
            ->orderByRaw("DATE(fecha_venta) ASC")
            ->get();
    }

    public function getSalesFrequencyByRangePaginated(Carbon $from, Carbon $to, int $perPage = 30)
    {
        return Sale::query()
            ->whereBetween('fecha_venta', [$from, $to])
            ->where('tipo_documento', 'FACTURA')
            ->selectRaw("DATE(fecha_venta) as dia")
            ->selectRaw('COUNT(sales.id) as comprobantes')
            ->selectRaw('SUM(sales.total) as total')
            ->groupByRaw("DATE(fecha_venta)")
            ->orderByRaw("DATE(fecha_venta) ASC")
            ->paginate($perPage, ['*'], 'range_page');
    }

    public function getTopProductsByQty(Carbon $from, Carbon $to, int $limit = 5): Collection
    {
        return Sale::query()
            ->join('sale_items as si', 'si.sale_id', '=', 'sales.id')
            ->join('products as p', 'p.id', '=', 'si.producto_id')
            ->whereBetween('sales.fecha_venta', [$from, $to])
            ->where('sales.tipo_documento', 'FACTURA')
            ->selectRaw('p.id as producto_id')
            ->selectRaw('p.nombre as producto_nombre')
            ->selectRaw('SUM(si.cantidad) as cantidad')
            ->selectRaw('SUM(si.total) as total')
            ->groupByRaw('p.id, p.nombre')
            ->orderByRaw('SUM(si.cantidad) DESC')
            ->limit($limit)
            ->get();
    }

    public function getInventoryReport(array $filters, int $perPage = 50)
    {
        $query = $this->buildInventoryQuery($filters);

        return $query->paginate($perPage)->withQueryString();
    }

    public function getInventoryChartData(array $filters, int $limit = 10): Collection
    {
        $query = $this->buildInventoryQuery($filters);

        return $query->limit($limit)->get();
    }

    public function getProductCategories(): Collection
    {
        return Product::query()
            ->whereNotNull('categoria')
            ->where('categoria', '!=', '')
            ->select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');
    }

    protected function buildInventoryQuery(array $filters)
    {
        $bodegaId = (int) ($filters['bodega_id'] ?? 0);
        $categoria = trim((string) ($filters['categoria'] ?? ''));
        $q = trim((string) ($filters['q'] ?? ''));

        $query = Inventory::query()
            ->join('products as p', 'p.id', '=', 'inventario.producto_id')
            ->join('bodegas as b', 'b.id', '=', 'inventario.bodega_id')
            ->selectRaw('p.id as producto_id')
            ->selectRaw('p.nombre as producto_nombre')
            ->selectRaw('p.categoria as categoria')
            ->selectRaw('p.stock_minimo as stock_minimo')
            ->selectRaw('b.id as bodega_id')
            ->selectRaw('b.nombre as bodega_nombre')
            ->selectRaw('SUM(inventario.stock_actual) as stock_actual')
            ->selectRaw('SUM(inventario.stock_reservado) as stock_reservado')
            ->groupByRaw('p.id, p.nombre, p.categoria, p.stock_minimo, b.id, b.nombre');

        if ($bodegaId > 0) {
            $query->where('inventario.bodega_id', $bodegaId);
        }

        if ($categoria !== '') {
            $query->where('p.categoria', $categoria);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('p.nombre', 'like', "%{$q}%")
                    ->orWhere('p.codigo_interno', 'like', "%{$q}%")
                    ->orWhere('p.codigo_barras', 'like', "%{$q}%");
            });
        }

        $query->orderByRaw('CASE WHEN SUM(inventario.stock_actual) < p.stock_minimo THEN 0 ELSE 1 END ASC');
        $query->orderByRaw('SUM(inventario.stock_actual) ASC');
        $query->orderBy('p.nombre');

        return $query;
    }
}
