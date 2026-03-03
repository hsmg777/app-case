<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Inventory;
use App\Models\Product\Product;
use App\Models\Store\Bodega;
use App\Models\Store\Percha;
use App\Repositories\Inventory\InventoryRepository;
use App\Repositories\Inventory\InventoryAdjustmentRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Exception;

class InventoryService
{
    protected InventoryRepository $repository;
    protected InventoryAdjustmentRepository $adjustmentRepository;

    public function __construct(
        InventoryRepository $repository,
        InventoryAdjustmentRepository $adjustmentRepository
    ) {
        $this->repository = $repository;
        $this->adjustmentRepository = $adjustmentRepository;
    }

    /* =========================================================
        MÉTODOS DE CONSULTA
       ========================================================= */

    public function getAll()
    {
        return $this->repository->all();
    }

    public function getTablePage(
        ?string $search = null,
        ?int $bodegaId = null,
        ?string $categoria = null,
        bool $onlyLow = false,
        int $page = 1,
        int $perPage = 20
    ): array {
        $paginator = $this->repository->paginateForTable(
            search: $search,
            bodegaId: $bodegaId,
            categoria: $categoria,
            onlyLow: $onlyLow,
            page: $page,
            perPage: $perPage
        );

        $rows = collect($paginator->items())->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'producto_id' => (int) $row->producto_id,
                'bodega_id' => (int) $row->bodega_id,
                'percha_id' => $row->percha_id !== null ? (int) $row->percha_id : null,
                'stock_actual' => (int) $row->stock_actual,
                'stock_reservado' => (int) ($row->stock_reservado ?? 0),
                'producto' => [
                    'id' => (int) $row->producto_id,
                    'nombre' => $row->producto_nombre,
                    'codigo_interno' => $row->producto_codigo_interno,
                    'codigo_barras' => $row->producto_codigo_barras,
                    'categoria' => $row->producto_categoria,
                    'stock_minimo' => (int) ($row->producto_stock_minimo ?? 0),
                ],
                'bodega' => [
                    'id' => (int) $row->bodega_id,
                    'nombre' => $row->bodega_nombre,
                ],
                'percha' => $row->percha_id !== null ? [
                    'id' => (int) $row->percha_id,
                    'codigo' => $row->percha_codigo,
                ] : null,
            ];
        })->all();

        return [
            'data' => $rows,
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
            'filters' => $this->repository->getFilterOptions(),
        ];
    }

    public function getById($id)
    {
        return $this->repository->find($id);
    }

    public function getByProduct($productoId)
    {
        return $this->repository->getByProduct($productoId);
    }

    public function getByBodega($bodegaId)
    {
        return $this->repository->getByBodega($bodegaId);
    }

    /* =========================================================
        CRUD BÁSICO
       ========================================================= */

    public function create($data)
    {
        return $this->repository->create($data);
    }

    public function update($id, $data)
    {
        $inv = $this->repository->find($id);
        return $this->repository->update($inv, $data);
    }

    public function delete($id)
    {
        $inv = $this->repository->find($id);
        $this->repository->delete($inv);
        return true;
    }

    /* =========================================================
        HELPERS INTERNOS
       ========================================================= */

    /**
     * Obtiene el registro de inventario para la ubicación,
     * y si no existe LO CREA con stock 0.
     */
    protected function getOrCreateLocation($productoId, $bodegaId, $perchaId): Inventory
    {
        /** @var Inventory|null $inv */
        $inv = $this->repository->getByLocation($productoId, $bodegaId, $perchaId);

        if (!$inv) {
            $inv = $this->repository->create([
                'producto_id' => $productoId,
                'bodega_id' => $bodegaId,
                'percha_id' => $perchaId,
                'stock_actual' => 0,
                'stock_reservado' => 0,
            ]);
        }

        return $inv;
    }

    /* =========================================================
        OPERACIONES DE STOCK
       ========================================================= */

    /**
     * Aumenta stock en una ubicación.
     * - Si no existe inventario para esa ubicación, lo crea.
     * - Registra el movimiento en ajustes_inventario con un motivo.
     *
     * $motivo:
     *   - null  => "Aumento de stock"
     *   - "Compra #ID" => cuando viene desde una compra
     */
    public function increaseStock(
        $productoId,
        $bodegaId,
        $perchaId,
        $cantidad,
        ?string $motivo = null
    ) {
        $cantidad = (int) $cantidad;
        if ($cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad a aumentar debe ser mayor a 0");
        }

        // 👉 Aquí ya se crea el inventario si no existía
        $inv = $this->getOrCreateLocation($productoId, $bodegaId, $perchaId);

        $stockInicial = (int) $inv->stock_actual;

        // Actualizar stock
        $inv = $this->repository->increaseStock($inv, $cantidad);

        // Registrar el movimiento en ajustes_inventario (kardex simple)
        $ajuste = $this->adjustmentRepository->create([
            'usuario_id' => Auth::id(),
            'bodega_id' => $inv->bodega_id,
            'percha_id' => $inv->percha_id,
            'producto_id' => $inv->producto_id,
            'stock_inicial' => $stockInicial,
            'stock_final' => $inv->stock_actual,
            'diferencia' => $cantidad,
            'tipo' => 'positivo',
            'motivo' => $motivo ?: 'Aumento de stock',
        ]);

        return [
            'message' => "Se aumentó el stock en {$cantidad} unidades.",
            'inventory' => $inv,
            'diferencia' => $cantidad,
            'tipo' => 'positivo',
            'ajuste' => $ajuste,
        ];
    }

    public function decreaseStock($productoId, $bodegaId, $perchaId, $cantidad)
    {
        /** @var Inventory|null $inv */
        $inv = $this->repository->getByLocation($productoId, $bodegaId, $perchaId);

        if (!$inv) {
            throw new Exception("No se encontró inventario para esa ubicación");
        }

        $cantidad = (int) $cantidad;

        if ($inv->stock_actual < $cantidad) {
            throw new Exception("Stock insuficiente");
        }

        $stockInicial = (int) $inv->stock_actual;

        $inv = $this->repository->decreaseStock($inv, $cantidad);

        $diferencia = -$cantidad;

        $ajuste = $this->adjustmentRepository->create([
            'usuario_id' => Auth::id(),
            'bodega_id' => $inv->bodega_id,
            'percha_id' => $inv->percha_id,
            'producto_id' => $inv->producto_id,
            'stock_inicial' => $stockInicial,
            'stock_final' => $inv->stock_actual,
            'diferencia' => $diferencia,
            'tipo' => 'negativo',
            'motivo' => 'Disminución manual de stock',
        ]);

        return [
            'message' => "Se disminuyó el stock en {$cantidad} unidades.",
            'inventory' => $inv,
            'diferencia' => $diferencia,
            'tipo' => 'negativo',
            'ajuste' => $ajuste,
        ];
    }

    /**
     * Ajusta el stock a un valor absoluto
     * y registra el ajuste en la tabla ajustes_inventario.
     */
    public function adjustStock($productoId, $bodegaId, $perchaId, int $nuevoStock, ?string $motivo = null)
    {
        /** @var Inventory|null $inv */
        $inv = $this->repository->getByLocation($productoId, $bodegaId, $perchaId);

        if (!$inv) {
            throw new Exception("No se encontró inventario para esa ubicación");
        }

        if ($nuevoStock < 0) {
            throw new InvalidArgumentException("El stock no puede ser negativo");
        }

        $stockInicial = (int) $inv->stock_actual;

        if ($nuevoStock === $stockInicial) {
            return [
                'message' => 'El stock ya tiene ese valor. No se realizaron cambios.',
                'inventory' => $inv,
                'diferencia' => 0,
                'tipo' => 'sin_cambios',
            ];
        }

        $diferencia = $nuevoStock - $stockInicial;
        $tipo = $diferencia > 0 ? 'positivo' : 'negativo';

        $inv = $this->repository->adjustStock($inv, $nuevoStock);

        $ajuste = $this->adjustmentRepository->create([
            'usuario_id' => Auth::id(),
            'bodega_id' => $inv->bodega_id,
            'percha_id' => $inv->percha_id,
            'producto_id' => $inv->producto_id,
            'stock_inicial' => $stockInicial,
            'stock_final' => $nuevoStock,
            'diferencia' => $diferencia,
            'tipo' => $tipo,
            'motivo' => $motivo,
        ]);

        return [
            'message' => $tipo === 'positivo'
                ? "Se aumentó el stock en {$diferencia} unidades."
                : "Se disminuyó el stock en " . abs($diferencia) . " unidades.",
            'inventory' => $inv,
            'diferencia' => $diferencia,
            'tipo' => $tipo,
            'ajuste' => $ajuste,
        ];
    }

    /**
     * Disminuye stock para una VENTA.
     *
     * - Si $perchaId es null, busca cualquier inventario de ese producto en esa bodega
     *   (ignora la percha).
     * - Si no existe inventario en esa bodega, crea uno con stock 0.
     * - Permite que el stock quede en negativo (venta sin stock).
     *
     * @return bool true  = había stock suficiente antes de la venta
     *              false = no alcanzaba el stock (se vendió sin stock)
     */
    public function decreaseStockForSale(
        $productoId,
        $bodegaId,
        $perchaId,
        $cantidad,
        ?int $usuarioId = null,
        ?int $saleId = null,
        ?string $numFactura = null
    ): bool {
        $cantidad = (int) $cantidad;
        if ($cantidad <= 0) {
            throw new InvalidArgumentException("La cantidad a disminuir debe ser mayor a 0");
        }

        $usuarioFinal = $usuarioId ?? Auth::id();
        $motivo = 'Disminución por venta';
        if ($saleId) {
            $motivo = "Venta #{$saleId}" . ($numFactura ? " ({$numFactura})" : "");
        }

        // CASO 1: Percha específica
        if (!is_null($perchaId)) {
            $inv = $this->repository->getByLocation($productoId, $bodegaId, $perchaId);

            if (!$inv) {
                // Si no existe, se crea para que quede en negativo
                $inv = $this->getOrCreateLocation($productoId, $bodegaId, $perchaId);
            }

            $stockInicial = (int) $inv->stock_actual;
            $teniaStock = $stockInicial >= $cantidad;

            $inv = $this->repository->decreaseStock($inv, $cantidad);

            $this->adjustmentRepository->create([
                'usuario_id' => $usuarioFinal,
                'bodega_id' => $inv->bodega_id,
                'percha_id' => $inv->percha_id,
                'producto_id' => $inv->producto_id,
                'stock_inicial' => $stockInicial,
                'stock_final' => $inv->stock_actual,
                'diferencia' => -$cantidad,
                'tipo' => 'negativo',
                'motivo' => $motivo,
            ]);

            return $teniaStock;
        }

        // CASO 2: Percha automática (null) -> Barrido inteligente
        // Buscamos todos los registros con stock > 0, ordenados (puedes ajustar el orden)
        $inventarios = Inventory::where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->where('stock_actual', '>', 0)
            ->orderBy('id') // FIFO por ID, o podrías usar 'created_at', o prioridad de percha
            ->get();

        $faltante = $cantidad;
        $algunoSinStock = false;

        // 1) Consumir de donde HAYA stock positivo
        foreach ($inventarios as $inv) {
            if ($faltante <= 0)
                break;

            $disponible = (int) $inv->stock_actual;
            $aDescontar = min($disponible, $faltante);

            $stockInicial = $disponible;

            // Bajamos stock
            $inv = $this->repository->decreaseStock($inv, $aDescontar);

            // Registramos ajuste parcial
            $this->adjustmentRepository->create([
                'usuario_id' => $usuarioFinal,
                'bodega_id' => $inv->bodega_id,
                'percha_id' => $inv->percha_id,
                'producto_id' => $inv->producto_id,
                'stock_inicial' => $stockInicial,
                'stock_final' => $inv->stock_actual,
                'diferencia' => -$aDescontar,
                'tipo' => 'negativo',
                'motivo' => $motivo . " (Auto)",
            ]);

            $faltante -= $aDescontar;
        }

        // 2) Si todavía falta (stock total era insuficiente),
        //    tenemos que descontar el remanente de ALGÚN lado para que quede negativo.
        //    Usaremos el último inventario tocado o buscaremos uno por defecto (sin percha o primera percha).
        if ($faltante > 0) {
            $algunoSinStock = true;

            // Intentamos buscar uno por defecto (ej: percha_id = null o el primero que exista)
            // Si $inventarios tenía elementos, usamos el último; si no, buscamos cualquiera o creamos.
            $targetInv = $inventarios->last();

            if (!$targetInv) {
                // No había ninguno con stock > 0. Buscamos CUALQUIERA (incluso con 0 o negativo)
                $targetInv = Inventory::where('producto_id', $productoId)
                    ->where('bodega_id', $bodegaId)
                    ->first();
            }

            if (!$targetInv) {
                // Si de plano no existe NADA en esa bodega, creamos uno (percha null por defecto o lo que definas)
                $targetInv = $this->getOrCreateLocation($productoId, $bodegaId, null);
            }

            // Descontamos lo que falta (llevándolo a negativo)
            $stockInicial = (int) $targetInv->stock_actual;

            $targetInv = $this->repository->decreaseStock($targetInv, $faltante);

            $this->adjustmentRepository->create([
                'usuario_id' => $usuarioFinal,
                'bodega_id' => $targetInv->bodega_id,
                'percha_id' => $targetInv->percha_id,
                'producto_id' => $targetInv->producto_id,
                'stock_inicial' => $stockInicial,
                'stock_final' => $targetInv->stock_actual,
                'diferencia' => -$faltante,
                'tipo' => 'negativo',
                'motivo' => $motivo . " (Saldo negativo)",
            ]);
        }

        return !$algunoSinStock;
    }



    public function transferBetweenWarehouses(
        int $bodegaOrigenId,
        int $bodegaDestinoId,
        array $items,
        ?string $observaciones = null
    ): array {
        if ($bodegaOrigenId === $bodegaDestinoId) {
            throw new InvalidArgumentException('La bodega origen y destino deben ser diferentes.');
        }

        if (empty($items)) {
            throw new InvalidArgumentException('Debes enviar al menos un producto para transferir.');
        }

        /** @var Bodega $bodegaOrigen */
        $bodegaOrigen = Bodega::findOrFail($bodegaOrigenId);
        /** @var Bodega $bodegaDestino */
        $bodegaDestino = Bodega::findOrFail($bodegaDestinoId);

        $itemsConsolidados = [];
        foreach ($items as $item) {
            $productoId = (int) ($item['producto_id'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);
            $perchaDestinoId = isset($item['percha_destino_id']) && $item['percha_destino_id'] !== ''
                ? (int) $item['percha_destino_id']
                : null;

            if ($productoId <= 0 || $cantidad <= 0) {
                throw new InvalidArgumentException('Los items de transferencia son invalidos.');
            }

            if (!is_null($perchaDestinoId)) {
                $perchaDestinoValida = Percha::where('id', $perchaDestinoId)
                    ->where('bodega_id', $bodegaDestinoId)
                    ->exists();
                if (!$perchaDestinoValida) {
                    throw new InvalidArgumentException('La percha destino no pertenece a la bodega destino seleccionada.');
                }
            }

            $key = $productoId . ':' . ($perchaDestinoId ?? 'null');
            if (!isset($itemsConsolidados[$key])) {
                $itemsConsolidados[$key] = [
                    'producto_id' => $productoId,
                    'percha_destino_id' => $perchaDestinoId,
                    'cantidad' => 0,
                ];
            }
            $itemsConsolidados[$key]['cantidad'] += $cantidad;
        }

        $detalleTransferencia = [];
        $motivoBase = "Transferencia {$bodegaOrigen->nombre} -> {$bodegaDestino->nombre}";
        if ($observaciones) {
            $motivoBase .= " | {$observaciones}";
        }

        DB::transaction(function () use (
            $itemsConsolidados,
            $bodegaOrigenId,
            $bodegaDestinoId,
            $motivoBase,
            &$detalleTransferencia
        ) {
            foreach ($itemsConsolidados as $itemConsolidado) {
                $productoId = (int) $itemConsolidado['producto_id'];
                $cantidad = (int) $itemConsolidado['cantidad'];
                $perchaDestinoId = $itemConsolidado['percha_destino_id'];

                /** @var Product $producto */
                $producto = Product::findOrFail($productoId);

                $inventariosOrigen = Inventory::where('producto_id', $productoId)
                    ->where('bodega_id', $bodegaOrigenId)
                    ->lockForUpdate()
                    ->get();

                $stockDisponible = (int) $inventariosOrigen
                    ->where('stock_actual', '>', 0)
                    ->sum('stock_actual');

                if ($stockDisponible < $cantidad) {
                    throw new InvalidArgumentException(
                        "Stock insuficiente para {$producto->nombre} en la bodega origen. Disponible: {$stockDisponible}."
                    );
                }

                $faltante = $cantidad;
                foreach ($inventariosOrigen->where('stock_actual', '>', 0)->sortBy('id') as $invOrigen) {
                    if ($faltante <= 0) {
                        break;
                    }

                    $descontar = min((int) $invOrigen->stock_actual, $faltante);
                    $stockInicial = (int) $invOrigen->stock_actual;

                    $this->repository->decreaseStock($invOrigen, $descontar);

                    $this->adjustmentRepository->create([
                        'usuario_id' => Auth::id(),
                        'bodega_id' => $invOrigen->bodega_id,
                        'percha_id' => $invOrigen->percha_id,
                        'producto_id' => $invOrigen->producto_id,
                        'stock_inicial' => $stockInicial,
                        'stock_final' => $invOrigen->stock_actual,
                        'diferencia' => -$descontar,
                        'tipo' => 'negativo',
                        'motivo' => $motivoBase . ' (Salida)',
                    ]);

                    $faltante -= $descontar;
                }

                if ($faltante > 0) {
                    throw new InvalidArgumentException(
                        "No se pudo completar la salida de {$producto->nombre} en la bodega origen."
                    );
                }

                $invDestino = $this->getOrCreateLocation($productoId, $bodegaDestinoId, $perchaDestinoId);
                $stockInicialDestino = (int) $invDestino->stock_actual;
                $this->repository->increaseStock($invDestino, $cantidad);

                $this->adjustmentRepository->create([
                    'usuario_id' => Auth::id(),
                    'bodega_id' => $invDestino->bodega_id,
                    'percha_id' => $invDestino->percha_id,
                    'producto_id' => $invDestino->producto_id,
                    'stock_inicial' => $stockInicialDestino,
                    'stock_final' => $invDestino->stock_actual,
                    'diferencia' => $cantidad,
                    'tipo' => 'positivo',
                    'motivo' => $motivoBase . ' (Entrada)',
                ]);

                $detalleTransferencia[] = [
                    'producto_id' => $productoId,
                    'producto' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'percha_destino_id' => $perchaDestinoId,
                ];
            }
        });

        return [
            'message' => 'Transferencia registrada correctamente.',
            'bodega_origen_id' => $bodegaOrigenId,
            'bodega_destino_id' => $bodegaDestinoId,
            'items' => array_values($detalleTransferencia),
        ];
    }

    /**
     * Historial de ajustes de stock por producto/bodega/percha.
     */
    public function getAdjustmentsHistory($productoId, $bodegaId, $perchaId = null)
    {
        return $this->adjustmentRepository->getByLocation($productoId, $bodegaId, $perchaId);
    }
}
