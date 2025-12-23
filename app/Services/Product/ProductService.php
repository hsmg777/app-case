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
        $inventarios = Inventory::with([
                'producto.price',
                'producto.productPrices',
            ])
            ->where('bodega_id', $bodegaId)
            ->where('stock_actual', '>', 0)
            ->get();

        return $inventarios
            ->filter(fn ($inv) => $inv->producto)
            ->map(function ($inv) {
                $product = $inv->producto;

                $data = $product->toArray();
                $data['stock_actual'] = $inv->stock_actual;
                $data['bodega_id']    = $inv->bodega_id;

                return $data;
            })
            ->values();
    }

    public function setEstado(string $id, bool $estado): void
    {
        $product = $this->repo->find($id);
        $this->repo->setEstado($product, $estado);
    }
}
