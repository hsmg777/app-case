<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected $service;

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


    /*
    |--------------------------------------------------------------------------
    | MÉTODOS FUNCIONALES (JSON)
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return response()->json($this->service->getAll());
    }

    public function show($id)
    {
        return response()->json($this->service->getById($id));
    }

    public function getByProduct($productoId)
    {
        return response()->json($this->service->getByProduct($productoId));
    }

    public function getByBodega($bodegaId)
    {
        return response()->json($this->service->getByBodega($bodegaId));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'producto_id' => 'required|exists:products,id',
            'bodega_id'   => 'required|exists:bodegas,id',
            'percha_id'   => 'nullable|exists:perchas,id',
            'stock_actual' => 'required|integer',
            'stock_reservado' => 'nullable|integer|min:0',
        ]);

        return response()->json($this->service->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'producto_id' => 'sometimes|exists:products,id',
            'bodega_id'   => 'sometimes|exists:bodegas,id',
            'percha_id'   => 'nullable|exists:perchas,id',
            'stock_actual' => 'sometimes|integer',
            'stock_reservado' => 'nullable|integer|min:0',
        ]);

        return response()->json($this->service->update($id, $data));
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Inventario eliminado correctamente']);
    }

    public function increaseStock(Request $request)
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

    public function decreaseStock(Request $request)
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

    
}
