<?php

namespace App\Repositories\Product;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository
{
    protected function buildListQuery(
        ?bool $onlyActive = true,
        ?string $search = null,
        ?string $categoria = null
    ): Builder {
        $query = Product::query()
            ->select(['id', 'nombre', 'codigo_interno', 'codigo_barras', 'categoria', 'stock_minimo', 'estado'])
            ->orderBy('nombre', 'asc');

        if ($onlyActive !== null) {
            $query->where('estado', $onlyActive);
        }

        if ($categoria !== null && $categoria !== '') {
            $query->where('categoria', $categoria);
        }

        if ($search !== null && $search !== '') {
            $query->where(function (Builder $subQuery) use ($search) {
                $subQuery->where('nombre', 'like', '%' . $search . '%')
                    ->orWhere('codigo_interno', 'like', '%' . $search . '%')
                    ->orWhere('codigo_barras', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

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

    public function getForExport(
        ?bool $onlyActive = true,
        ?string $search = null,
        ?string $categoria = null
    ) {
        $query = Product::query()
            ->select(['id', 'nombre', 'codigo_interno', 'codigo_barras', 'categoria', 'estado'])
            ->orderBy('nombre', 'asc');

        if ($onlyActive !== null) {
            $query->where('estado', $onlyActive);
        }

        if ($categoria !== null && $categoria !== '') {
            $query->where('categoria', $categoria);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('nombre', 'like', '%' . $search . '%')
                    ->orWhere('codigo_interno', 'like', '%' . $search . '%')
                    ->orWhere('codigo_barras', 'like', '%' . $search . '%');
            });
        }

        return $query->get();
    }

    public function allForPos(?bool $onlyActive = true)
    {
        $query = Product::query()
            ->select([
                'id',
                'nombre',
                'codigo_barras',
                'codigo_interno',
                'categoria',
                'unidad_medida',
                'iva_porcentaje',
                'estado',
            ])
            ->with([
                'price:id,producto_id,precio_unitario,precio_por_cantidad,cantidad_min,cantidad_max,precio_por_caja,unidades_por_caja,moneda',
            ])
            ->orderBy('nombre', 'asc');

        if ($onlyActive !== null) {
            $query->where('estado', $onlyActive);
        }

        return $query->get();
    }

    public function paginateForTable(
        ?bool $onlyActive = true,
        ?string $search = null,
        ?string $categoria = null,
        int $page = 1,
        int $perPage = 10
    ) {
        return $this->buildListQuery($onlyActive, $search, $categoria)
            ->paginate(
                perPage: $perPage,
                columns: ['*'],
                pageName: 'page',
                page: $page
            );
    }

    public function getDistinctCategories(?bool $onlyActive = true): array
    {
        $query = Product::query()
            ->select('categoria')
            ->whereNotNull('categoria')
            ->where('categoria', '!=', '');

        if ($onlyActive !== null) {
            $query->where('estado', $onlyActive);
        }

        return $query
            ->distinct()
            ->orderBy('categoria', 'asc')
            ->pluck('categoria')
            ->all();
    }

    public function existsByCodigoInterno(string $codigoInterno): bool
    {
        return Product::query()
            ->where('codigo_interno', $codigoInterno)
            ->exists();
    }

    public function existsByCodigoBarras(string $codigoBarras): bool
    {
        return Product::query()
            ->where('codigo_barras', $codigoBarras)
            ->exists();
    }
}
