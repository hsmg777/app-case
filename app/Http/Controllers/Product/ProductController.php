<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Services\Product\ProductService;
use Illuminate\Http\Request;


class ProductController extends Controller
{
    protected $service;

    public function __construct(ProductService $service)
    {
        $this->service = $service;
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS PARA VISTAS (BLADE)
    |--------------------------------------------------------------------------
    */

    public function viewIndex()
    {
        return view('inventario.productos.index');
    }

    public function viewCreate()
    {
        return view('inventario.productos.create');
    }

    public function viewEdit($id)
    {
        $producto = $this->service->getById($id);
        return view('inventario.productos.edit', compact('producto'));
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS API (JSON)
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $bodegaId = $request->query('bodega_id');

        if ($bodegaId) {
            return response()->json(
                $this->service->getByBodegaWithStock((int) $bodegaId)
            );
        }

        $estado = $request->query('estado', 'activos');

        $onlyActive = true;
        if ($estado === 'inactivos') $onlyActive = false;
        if ($estado === 'todos') $onlyActive = null;

        return response()->json($this->service->getAll($onlyActive));
    }


    public function show($id)
    {
        return response()->json($this->service->getById($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'codigo_barras' => 'nullable|string|max:255|unique:products,codigo_barras',
            'codigo_interno' => 'nullable|string|max:255|unique:products,codigo_interno',
            'categoria' => 'nullable|string|max:255',
            'foto_url' => 'nullable|string',
            'unidad_medida' => 'required|string|max:50',
            'stock_minimo' => 'required|integer|min:0',

            'iva_porcentaje' => 'nullable|numeric|min:0|max:100',

            'estado' => 'boolean',
        ]);

        return response()->json($this->service->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'codigo_barras' => "nullable|string|max:255|unique:products,codigo_barras,$id",
            'codigo_interno' => "nullable|string|max:255|unique:products,codigo_interno,$id",
            'categoria' => 'nullable|string|max:255',
            'foto_url' => 'nullable|string',
            'unidad_medida' => 'sometimes|string|max:50',
            'stock_minimo' => 'sometimes|integer|min:0',
            'iva_porcentaje' => 'nullable|numeric|min:0|max:100',

            'estado' => 'boolean',
        ]);

        return response()->json($this->service->update($id, $data));
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    public function setEstado(Request $request, string $id)
    {
        $data = $request->validate([
            'estado' => ['required', 'boolean'],
        ]);

        $this->service->setEstado($id, (bool) $data['estado']);

        return response()->json([
            'message' => $data['estado'] ? 'Producto activado' : 'Producto desactivado',
        ]);
    }
}
