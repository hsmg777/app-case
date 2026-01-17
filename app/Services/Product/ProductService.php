<?php

namespace App\Services\Product;

use App\Repositories\Product\ProductRepository;
use App\Models\Inventory\Inventory;

class ProductService
{
    protected ProductRepository $repo;

    public function __construct(ProductRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getAll(?bool $onlyActive = true)
    {
        return $this->repo->all(
            onlyActive: $onlyActive,
            withPrice: true,
            withTierPrices: true
        );
    }

    public function getById($id)
    {
        return $this->repo->find(
            $id,
            withPrice: true,
            withTierPrices: true
        );
    }

    public function create(array $data)
    {
        if (
            !array_key_exists('iva_porcentaje', $data) ||
            $data['iva_porcentaje'] === null ||
            $data['iva_porcentaje'] === ''
        ) {
            $data['iva_porcentaje'] = 15.00;
        }

        return $this->repo->create($data);
    }

    public function update($id, array $data)
    {
        $product = $this->repo->find($id);

        if (
            array_key_exists('iva_porcentaje', $data) &&
            ($data['iva_porcentaje'] === null || $data['iva_porcentaje'] === '')
        ) {
            $data['iva_porcentaje'] = 15.00;
        }

        return $this->repo->update($product, $data);
    }

    public function delete($id)
    {
        $product = $this->repo->find($id);
        return $this->repo->delete($product);
    }

    public function getByBodegaWithStock(int $bodegaId)
    {
        // 1. Traer TODOS los productos activos (o todos si se requiere) + Relación Inventario (todas las bodegas)
        // Eliminamos el filtro 'stock_actual > 0' para traer todo.
        // Y traemos inventario de TODAS las bodegas para hacer el global search.

        $products = $this->repo->all(onlyActive: true, withPrice: true, withTierPrices: true);

        // Eager load manual para optimizar o usar el query builder.
        // Pero como $repo->all retorna Collection de modelos, cargamos relaciones:
        $products->load(['inventory.percha', 'inventory.bodega']);

        $results = [];

        foreach ($products as $product) {
            $prodData = $product->toArray();

            // Agrupar inventarios de ESTE producto por bodega_id
            $invByBodega = $product->inventory->groupBy('bodega_id');

            // Lista de bodegas que tienen registro de inventario
            $existingBodegas = $invByBodega->keys()->all();

            // Asegurar que la bodega actual ($bodegaId) esté en la lista a procesar,
            // aunque no tenga inventario (para mostrarla con stock 0).
            $bodegasToProcess = array_unique(array_merge($existingBodegas, [$bodegaId]));

            foreach ($bodegasToProcess as $bId) {
                // Filtrar inventarios para esta bodega especifica
                $invList = $invByBodega->get($bId) ?? collect();

                $totalStock = $invList->sum('stock_actual');

                // Construir lista de perchas
                $perchas = $invList->map(function ($inv) {
                    return [
                        'id' => $inv->percha_id,
                        'nombre' => $inv->percha ? $inv->percha->nombre : 'Sin percha',
                        'stock' => $inv->stock_actual,
                    ];
                })->values()->toArray();

                // Clonamos datos del producto base
                $row = $prodData;

                // Sobrescribimos/Agregamos datos de contexto bodega
                $row['stock_actual'] = $totalStock;
                $row['bodega_id'] = $bId;
                $row['perchas'] = $perchas; // Lista de perchas en ESTA bodega

                // Info extra de la bodega (nombre) si existe
                $firstInv = $invList->first();
                // Determinar nombre bodega
                if ($firstInv && $firstInv->bodega) {
                    $row['bodega_nombre'] = $firstInv->bodega->nombre;
                } else {
                    // Intento fallback si es la local y no hay inventario
                    $row['bodega_nombre'] = ($bId == $bodegaId) ? 'Bodega Actual' : "Bodega #$bId";
                }

                $results[] = $row;
            }
        }

        return $results;
    }

    public function setEstado(string $id, bool $estado): void
    {
        $product = $this->repo->find($id);
        $this->repo->setEstado($product, $estado);
    }
}
