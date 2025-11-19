<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Store\PerchaService;
use Illuminate\Http\Request;

class PerchaController extends Controller
{
    protected $service;

    public function __construct(PerchaService $service)
    {
        $this->service = $service;
    }

    /*
    |--------------------------------------------------------------------------
    | VISTA PRINCIPAL (misma vista de bodegas)
    |--------------------------------------------------------------------------
    */
    public function viewIndex()
    {
        return view('inventario.bodegas_perchas.index');
    }

    /*
    |--------------------------------------------------------------------------
    | API JSON
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

    public function getByBodega($bodegaId)
    {
        return response()->json($this->service->getByBodega($bodegaId));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bodega_id' => 'required|exists:bodegas,id',
            'codigo' => 'required|string|max:100',
            'descripcion' => 'nullable|string',
        ]);

        return response()->json($this->service->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'bodega_id' => 'sometimes|exists:bodegas,id',
            'codigo' => 'sometimes|string|max:100',
            'descripcion' => 'nullable|string',
        ]);

        return response()->json($this->service->update($id, $data));
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Percha eliminada correctamente']);
    }
}
