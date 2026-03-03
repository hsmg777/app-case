<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Store\Bodega;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InventoryController extends Controller
{
    protected InventoryService $service;

    public function __construct(InventoryService $service)
    {
        $this->service = $service;
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS QUE RETORNAN VISTAS
    |--------------------------------------------------------------------------
    */

    public function viewIndex()
    {
        return view('inventario.index');
    }

    public function viewStock()
    {
        return view('inventario.stock.stock');
    }

    public function viewTransfers()
    {
        $bodegas = Bodega::orderBy('nombre')->get();

        return view('inventario.transferencias.index', compact('bodegas'));
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS FUNCIONALES (JSON)
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('paginated')) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(10, min(100, (int) $request->query('per_page', 20)));
            $search = trim((string) $request->query('q', ''));
            $categoria = trim((string) $request->query('categoria', ''));
            $bodegaId = $request->query('bodega_id');
            $bodegaId = ($bodegaId !== null && $bodegaId !== '')
                ? (int) $bodegaId
                : null;
            $onlyLow = $request->boolean('only_low');

            return response()->json(
                $this->service->getTablePage(
                    search: $search !== '' ? $search : null,
                    bodegaId: $bodegaId,
                    categoria: $categoria !== '' ? $categoria : null,
                    onlyLow: $onlyLow,
                    page: $page,
                    perPage: $perPage
                )
            );
        }

        return response()->json($this->service->getAll());
    }

    public function show($id): JsonResponse
    {
        return response()->json($this->service->getById($id));
    }

    public function getByProduct($productoId): JsonResponse
    {
        return response()->json($this->service->getByProduct($productoId));
    }

    public function getByBodega($bodegaId): JsonResponse
    {
        return response()->json($this->service->getByBodega($bodegaId));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'producto_id'     => 'required|exists:products,id',
            'bodega_id'       => 'required|exists:bodegas,id',
            'percha_id'       => 'nullable|exists:perchas,id',
            'stock_actual'    => 'required|integer',
            'stock_reservado' => 'nullable|integer|min:0',
        ]);

        return response()->json($this->service->create($data), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $data = $request->validate([
            'producto_id'     => 'sometimes|exists:products,id',
            'bodega_id'       => 'sometimes|exists:bodegas,id',
            'percha_id'       => 'nullable|exists:perchas,id',
            'stock_actual'    => 'sometimes|integer',
            'stock_reservado' => 'nullable|integer|min:0',
        ]);

        return response()->json($this->service->update($id, $data));
    }

    public function destroy($id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json([
            'message' => 'Inventario eliminado correctamente',
        ]);
    }

    public function increaseStock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'producto_id' => 'required|exists:products,id',
            'bodega_id'   => 'required|exists:bodegas,id',
            'percha_id'   => 'nullable|exists:perchas,id',
            'cantidad'    => 'required|integer|min:1',
        ]);

        return response()->json(
            $this->service->increaseStock(
                $data['producto_id'],
                $data['bodega_id'],
                $data['percha_id'] ?? null,
                $data['cantidad']
            )
        );
    }

    public function decreaseStock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'producto_id' => 'required|exists:products,id',
            'bodega_id'   => 'required|exists:bodegas,id',
            'percha_id'   => 'nullable|exists:perchas,id',
            'cantidad'    => 'required|integer|min:1',
        ]);

        return response()->json(
            $this->service->decreaseStock(
                $data['producto_id'],
                $data['bodega_id'],
                $data['percha_id'] ?? null,
                $data['cantidad']
            )
        );
    }

    public function adjustStock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'producto_id' => 'required|exists:products,id',
            'bodega_id'   => 'required|exists:bodegas,id',
            'percha_id'   => 'nullable|exists:perchas,id',
            'nuevo_stock' => 'required|integer|min:0',
            'motivo'      => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->service->adjustStock(
                $data['producto_id'],
                $data['bodega_id'],
                $data['percha_id'] ?? null,
                $data['nuevo_stock'],
                $data['motivo'] ?? null
            )
        );
    }

    public function viewHistory(Request $request)
    {
        $productoId      = $request->query('producto_id');
        $bodegaId        = $request->query('bodega_id');
        $perchaId        = $request->query('percha_id');
        $productoNombre  = $request->query('producto_nombre');
        $bodegaNombre    = $request->query('bodega_nombre');
        $perchaCodigo    = $request->query('percha_codigo');

        return view('inventario.stock.historial', [
            'producto_id'     => $productoId,
            'bodega_id'       => $bodegaId,
            'percha_id'       => $perchaId,
            'producto_nombre' => $productoNombre,
            'bodega_nombre'   => $bodegaNombre,
            'percha_codigo'   => $perchaCodigo,
        ]);
    }

    public function adjustmentsHistory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'producto_id' => 'required|exists:products,id',
            'bodega_id'   => 'required|exists:bodegas,id',
            'percha_id'   => 'nullable|exists:perchas,id',
        ]);

        return response()->json(
            $this->service->getAdjustmentsHistory(
                $data['producto_id'],
                $data['bodega_id'],
                $data['percha_id'] ?? null
            )
        );
    }

    public function transferBetweenWarehouses(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bodega_origen_id'  => 'required|exists:bodegas,id|different:bodega_destino_id',
            'bodega_destino_id' => 'required|exists:bodegas,id',
            'observaciones'     => 'nullable|string|max:500',
            'items'             => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:products,id',
            'items.*.cantidad'    => 'required|integer|min:1',
            'items.*.percha_destino_id' => 'nullable|exists:perchas,id',
        ]);
        try {
            $result = $this->service->transferBetweenWarehouses(
                (int) $data['bodega_origen_id'],
                (int) $data['bodega_destino_id'],
                $data['items'],
                $data['observaciones'] ?? null
            );

            return response()->json($result);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }


}
