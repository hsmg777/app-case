<?php

namespace App\Services\Sales;

use App\Models\Product\Product;
use App\Repositories\Sales\SaleRepository;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Sales\Sale;
use App\Services\Cashier\CashierService;
use App\Models\Clients\ClientEmail;


class SaleService
{
    protected SaleRepository $sales;
    protected InventoryService $inventory;

    public function __construct(
        SaleRepository $sales,
        InventoryService $inventory,
        private CashierService $cashier
    ) {
        $this->sales     = $sales;
        $this->inventory = $inventory;
    }

    /**
     * Crea una venta completa (cabecera, ítems, pago, stock)
     * ✅ Recalcula precios por cantidad/caja en backend usando product_prices
     * ✅ Registra cash_movements IN si el pago fue en efectivo (caja abierta)
     */
    public function crearVenta(array $data): Sale
    {
        return DB::transaction(function () use ($data) {

            // ==========================
            // CAJA: exigir caja_id + sesión abierta
            // ==========================
            $cajaId = (int)($data['caja_id'] ?? 0);
            if ($cajaId <= 0) {
                throw ValidationException::withMessages([
                    'caja_id' => 'Debes indicar el número de caja (caja_id).',
                ]);
            }

            // Si no hay sesión abierta, NO se puede facturar
            $this->cashier->getOpenSessionOrFail($cajaId);

            $items   = $data['items']   ?? [];
            $payment = $data['payment'] ?? null;

            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => 'La venta debe tener al menos un ítem.',
                ]);
            }

            if (!$payment) {
                throw ValidationException::withMessages([
                    'payment' => 'Debe registrar al menos un pago.',
                ]);
            }

            // ==========================
            // IVA ON/OFF (GLOBAL)
            // ==========================
            $ivaEnabled = (bool)($data['iva_enabled'] ?? true);

            // ==========================
            // HELPERS EN CENTAVOS
            // ==========================
            $toCents = function ($n): int {
                $n = $n ?? 0;
                return (int) round(((float) $n) * 100, 0, PHP_ROUND_HALF_UP);
            };

            $fromCents = function (int $cents): float {
                return round($cents / 100, 2);
            };

            // Clamp % a 0..100 y pasar a basis points (2 decimales) => 15.00% => 1500
            $toBp = function ($pct): int {
                $p = (float)($pct ?? 0);
                if ($p < 0) $p = 0;
                if ($p > 100) $p = 100;
                return (int) round($p * 100, 0, PHP_ROUND_HALF_UP);
            };

            // ==========================
            // CALCULOS (CENTAVOS)
            // ==========================
            $subtotalCents  = 0;
            $descuentoCents = 0;
            $ivaCents       = 0;
            $impuestoCents  = 0;

            foreach ($items as $idx => &$item) {

                $productoId = (int)($item['producto_id'] ?? 0);
                $cantidad   = (int)($item['cantidad'] ?? 0);

                if ($productoId <= 0) {
                    throw ValidationException::withMessages([
                        "items.$idx.producto_id" => 'Producto inválido.',
                    ]);
                }

                if ($cantidad <= 0) {
                    throw ValidationException::withMessages([
                        "items.$idx.cantidad" => 'Cantidad debe ser válida.',
                    ]);
                }

                // ✅ Cargar producto + precios por reglas
                $product = Product::with(['price', 'product_prices'])->find($productoId);
                if (!$product) {
                    throw ValidationException::withMessages([
                        "items.$idx.producto_id" => 'El producto no existe.',
                    ]);
                }

                // ✅ Precio unitario REAL
                $pricing = $this->resolveLinePricingForQuantity($product, $cantidad, $toCents, $fromCents);
                $precioUnitario  = (float) $pricing['effective_unit_price'];

                if (!is_finite($precioUnitario) || $precioUnitario < 0) {
                    throw ValidationException::withMessages([
                        "items.$idx.precio_unitario" => 'Precio unitario inválido.',
                    ]);
                }


                // descuento es MONTO ($)
                $descCts = $toCents($item['descuento'] ?? 0);
                if ($descCts < 0) $descCts = 0;

                $lineSubtotalCts = (int) $pricing['line_subtotal_cents']; 

                if ($descCts > $lineSubtotalCts) {
                    throw ValidationException::withMessages([
                        "items.$idx.descuento" => 'El descuento no puede superar el valor de la línea.',
                    ]);
                }

                $lineBaseCts = $lineSubtotalCts - $descCts;

                // IVA % desde producto (fallback 15)
                $ivaPctProducto = $product->iva_porcentaje;
                if ($ivaPctProducto === null || $ivaPctProducto === '') {
                    $ivaPctProducto = 15;
                }

                $bp = $ivaEnabled ? $toBp($ivaPctProducto) : 0;

                // IVA exacto en centavos (HALF_UP):
                $lineIvaCts = (int) floor(($lineBaseCts * $bp + 5000) / 10000);

                // Guardamos valores recalculados
                $item['precio_unitario']  = $precioUnitario;
                $item['iva_porcentaje']   = (float) $ivaPctProducto;
                $item['pricing_rule']     = $pricing['rule'] ?? null;
                $item['pricing_price_id'] = $pricing['price_id'] ?? null;

                // Total SIN IVA
                $item['total'] = $fromCents($lineBaseCts);

                $subtotalCents  += $lineSubtotalCts;
                $descuentoCents += $descCts;
                $ivaCents       += $lineIvaCts;
            }
            unset($item);

            $baseImponibleCents = $subtotalCents - $descuentoCents;
            $totalCents = $baseImponibleCents + $impuestoCents + $ivaCents;

            $subtotal       = $fromCents($subtotalCents);
            $descuentoTotal = $fromCents($descuentoCents);
            $impuesto       = $fromCents($impuestoCents);
            $iva            = $fromCents($ivaCents);
            $total          = $fromCents($totalCents);

            $clientId = $data['client_id'] ?? null;
            $clientEmailId = $data['client_email_id'] ?? null;
            $emailDestino = $data['email_destino'] ?? null;

            if ($clientId && $clientEmailId) {
                $ok = ClientEmail::where('id', $clientEmailId)
                    ->where('client_id', $clientId)
                    ->exists();

                if (!$ok) {
                    throw ValidationException::withMessages([
                        'client_email_id' => 'El correo seleccionado no pertenece al cliente.',
                    ]);
                }

                if (!$emailDestino) {
                    $emailDestino = ClientEmail::where('id', $clientEmailId)->value('email');
                }
            }

            // ==========================
            // CREAR CABECERA DE VENTA
            // ==========================
            $saleData = [
                'client_id'      => $data['client_id'] ?? null,
                'user_id'        => $data['user_id'],
                'client_email_id' => $clientEmailId ?? null,
                'email_destino'   => $emailDestino ?? null,
                'bodega_id'      => $data['bodega_id'],
                'fecha_venta'    => $data['fecha_venta'],
                'tipo_documento' => $data['tipo_documento'] ?? 'FACTURA',
                'num_factura'    => $data['num_factura'] ?? null,
                'subtotal'       => $subtotal,
                'descuento'      => $descuentoTotal,
                'impuesto'       => $impuesto,
                'iva'            => $iva,
                'total'          => $total,
                'estado'         => 'pendiente',
                'observaciones'  => $data['observaciones'] ?? null,
            ];

            $sale = $this->sales->createSale($saleData);

            // ==========================
            // ITEMS + STOCK
            // ==========================
            $vendioSinStock = false;

            foreach ($items as $item) {
                $this->sales->addItem($sale, [
                    'producto_id'     => $item['producto_id'],
                    'descripcion'     => $item['descripcion'],
                    'cantidad'        => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento'       => $item['descuento'] ?? 0,
                    'total'           => $item['total'], // sin IVA
                ]);

                $teniaStock = $this->inventory->decreaseStockForSale(
                    $item['producto_id'],
                    $data['bodega_id'],
                    $item['percha_id'] ?? null,
                    $item['cantidad'],
                    $data['user_id'],
                    $sale->id,
                    $sale->num_factura
                );

                if (!$teniaStock) {
                    $vendioSinStock = true;
                }
            }

            // ==========================
            // PAGO
            // ==========================
            $montoRecibido = (float)($payment['monto_recibido'] ?? $total);
            $cambio        = $montoRecibido - $total;

            if ($montoRecibido < $total) {
                throw ValidationException::withMessages([
                    'payment.monto_recibido' => 'El monto recibido no puede ser menor al total de la venta.',
                ]);
            }

            $metodoPago = (string)($payment['metodo'] ?? '');
            $metodoPagoNorm = strtoupper(trim($metodoPago));

            $this->sales->addPayment($sale, [
                'fecha_pago'        => $payment['fecha_pago'] ?? now(),
                'monto'             => $total, // incluye IVA
                'metodo'            => $metodoPago,
                'payment_method_id' => $payment['payment_method_id'] ?? null,
                'referencia'        => $payment['referencia'] ?? null,
                'observaciones'     => $payment['observaciones'] ?? null,
                'monto_recibido'    => $montoRecibido,
                'cambio'            => $cambio,
                'usuario_id'        => $data['user_id'],
            ]);

            // ==========================
            // ESTADO
            // ==========================
            $this->sales->updateEstado($sale, 'pagada');

            // ==========================
            // ✅ REGISTRAR EN CAJA: SOLO EFECTIVO
            // ==========================
            $isCash = in_array($metodoPagoNorm, ['EFECTIVO', 'CASH'], true);

            if ($isCash) {
                $this->cashier->registerSaleIncome(
                    $cajaId,
                    (int) $data['user_id'],
                    (int) $sale->id,
                    $sale->num_factura,
                    (float) $total,
                    $metodoPagoNorm
                );
            }

            $sale = $this->sales->findById($sale->id);
            $sale->setAttribute('vendio_sin_stock', $vendioSinStock);

            return $sale;
        });
    }

    public function getById(int $id): ?Sale
    {
        return $this->sales->findById($id);
    }

    private function resolveLinePricingForQuantity(
        Product $product,
        int $qty,
        callable $toCents,
        callable $fromCents
    ): array {
        // OJO: ajusta el nombre de la relación a la tuya real:
        // si tu modelo usa product_prices(), usa product_prices
        $product->loadMissing(['price', 'product_prices']);

        $prices = $product->product_prices ?? collect();

        // ===== base unit =====
        $base = null;
        if ($product->relationLoaded('price') && $product->price) {
            $base = $product->price->precio_unitario ?? null;
        }
        if ($base === null || $base === '') {
            $base = $product->precio_unitario ?? 0;
        }
        $baseUnit = (float) $base;

        // ===== helper: mejor tier por cantidad (con fallback "se queda en el último") =====
        $pickTier = function () use ($prices, $qty) {
            // 1) match estricto (min..max)
            $tier = $prices
                ->filter(function ($pp) use ($qty) {
                    $min = (int)($pp->cantidad_min ?? 0);
                    $max = $pp->cantidad_max !== null ? (int)$pp->cantidad_max : null;
                    $pQ  = $pp->precio_por_cantidad;

                    if ($min <= 0) return false;
                    if ($pQ === null || $pQ === '') return false;
                    if ($qty < $min) return false;
                    if ($max !== null && $qty > $max) return false;
                    return true;
                })
                ->sortByDesc(fn ($pp) => (int)($pp->cantidad_min ?? 0))
                ->first();

            if ($tier) return $tier;

            // 2) fallback: si ya pasó el max y no hay más reglas, se queda en el último min aplicable
            return $prices
                ->filter(function ($pp) use ($qty) {
                    $min = (int)($pp->cantidad_min ?? 0);
                    $pQ  = $pp->precio_por_cantidad;
                    if ($min <= 0) return false;
                    if ($pQ === null || $pQ === '') return false;
                    return $qty >= $min;
                })
                ->sortByDesc(fn ($pp) => (int)($pp->cantidad_min ?? 0))
                ->first();
        };

        $tier = $pickTier();
        $tierUnit = $tier ? (float) $tier->precio_por_cantidad : $baseUnit;

        // ===== caja aplicable (mayor unidades_por_caja) =====
        $box = $prices
            ->filter(function ($pp) use ($qty) {
                $upc  = (int)($pp->unidades_por_caja ?? 0);
                $pBox = $pp->precio_por_caja;
                if ($upc <= 0) return false;
                if ($pBox === null || $pBox === '') return false;
                return $qty >= $upc;
            })
            ->sortByDesc(fn ($pp) => (int)($pp->unidades_por_caja ?? 0))
            ->first();

        // ===== si aplica caja: boxes*precioCaja + remainder*precioTier =====
        if ($box) {
            $upc = (int) $box->unidades_por_caja;
            $boxPriceCts = $toCents((float) $box->precio_por_caja);
            $tierUnitCts = $toCents($tierUnit);

            $boxes = intdiv($qty, $upc);
            $remainder = $qty % $upc;

            $lineSubtotalCts = ($boxes * $boxPriceCts) + ($remainder * $tierUnitCts);

            // unitario referencial para guardar
            $effectiveUnitCts = $qty > 0 ? (int) round($lineSubtotalCts / $qty) : $tierUnitCts;

            return [
                'line_subtotal_cents'   => $lineSubtotalCts,
                'effective_unit_price'  => $fromCents($effectiveUnitCts),
                'rule'                  => 'caja',
                'price_id'              => $box->id ?? null,
            ];
        }

        // ===== si NO aplica caja: qty * tierUnit =====
        $tierUnitCts = $toCents($tierUnit);
        $lineSubtotalCts = $qty * $tierUnitCts;

        return [
            'line_subtotal_cents'  => $lineSubtotalCts,
            'effective_unit_price' => $tierUnit,
            'rule'                 => $tier ? 'cantidad' : 'base',
            'price_id'             => $tier->id ?? null,
        ];
    }

}
