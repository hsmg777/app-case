<?php

namespace App\Repositories\Product;

use App\Models\Product\Product;

class ProductRepository
{
    public function all(
        ?bool $onlyActive = true,
        bool $withPrice = true,
        bool $withTierPrices = true
    ) {
        $query = Product::query()->orderBy('nombre', 'asc');

        $with = [];
        if ($withPrice) $with[] = 'price';
        if ($withTierPrices) $with[] = 'productPrices';
        if (!empty($with)) $query->with($with);

        if ($onlyActive !== null) {
            $query->where('estado', $onlyActive);
        }

        return $query->get();
    }

    public function find(
        $id,
        bool $withPrice = true,
        bool $withTierPrices = true
    ) {
        $query = Product::query();

        $with = [];
        if ($withPrice) $with[] = 'price';
        if ($withTierPrices) $with[] = 'productPrices';
        if (!empty($with)) $query->with($with);

        return $query->findOrFail($id);
    }

    public function create(array $data)
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data)
    {
        $product->update($data);
        return $product->refresh()->load(['price', 'productPrices']);
    }

    public function delete(Product $product)
    {
        return $product->delete();
    }

    public function findOrFail(string $id): Product
    {
        return Product::query()->findOrFail($id);
    }

    public function setEstado(Product $product, bool $estado): void
    {
        $product->estado = $estado;
        $product->save();
    }
}
