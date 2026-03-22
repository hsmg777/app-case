<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    protected SupplierService $service;

    public function __construct(SupplierService $service)
    {
        $this->service = $service;
    }

    // Menú del módulo
    public function viewMenu()
    {
        return view('inventario.proveedores.menu');
    }

    // Vista CRUD de proveedores
    public function viewIndex()
    {
        return view('inventario.proveedores.index');
    }

    // JSON
    public function index(): JsonResponse
    {
        return response()->json($this->service->getAll());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->service->getById($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'    => 'required|string|max:255',
            'ruc'       => 'nullable|string|max:13',
            'telefono'  => 'nullable|string|max:50',
            'email'     => 'nullable|email|max:255',
            'direccion' => 'nullable|string|max:255',
            'contacto'  => 'nullable|string|max:255', 
            'activo'    => 'nullable|boolean',
        ]);

        $data['activo'] = $data['activo'] ?? true;

        $supplier = $this->service->create($data);

        return response()->json($supplier, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'nombre'    => 'sometimes|required|string|max:255',
            'ruc'       => 'nullable|string|max:13',
            'telefono'  => 'nullable|string|max:50',
            'email'     => 'nullable|email|max:255',
            'direccion' => 'nullable|string|max:255',
            'contacto'  => 'nullable|string|max:255', 
            'activo'    => 'nullable|boolean',
        ]);

        $supplier = $this->service->update($id, $data);

        return response()->json($supplier);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json([
            'message' => 'Proveedor eliminado correctamente',
        ]);
    }
}
