<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\Store\BodegaService;
use Illuminate\Http\Request;

class BodegaController extends Controller
{
    protected $service;

    public function __construct(BodegaService $service)
    {
        $this->service = $service;
    }

    /*
    |--------------------------------------------------------------------------
    | VISTA PRINCIPAL DE BODEGAS + PERCHAS
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'ubicacion' => 'nullable|string|max:255',
            'tipo' => 'required|string|max:50',
        ]);

        return response()->json($this->service->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'ubicacion' => 'nullable|string|max:255',
            'tipo' => 'sometimes|string|max:50',
        ]);

        return response()->json($this->service->update($id, $data));
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return response()->json(['message' => 'Bodega eliminada correctamente']);
    }
}
