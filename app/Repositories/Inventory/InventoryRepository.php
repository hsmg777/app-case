<?php

namespace App\Repositories\Inventory;

use App\Models\Inventory\Inventory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InventoryRepository
{
    public function all()
    {
        return Inventory::with(['producto', 'bodega', 'percha'])
            ->orderBy('id', 'desc')
            ->get();
    }

    public function find($id)
    {
        return Inventory::with(['producto', 'bodega', 'percha'])
            ->findOrFail($id);
    }

    public function getByProduct($productoId)
    {
        return Inventory::with(['bodega', 'percha'])
            ->where('producto_id', $productoId)
            ->get();
    }

    public function getByBodega($bodegaId)
    {
        return Inventory::with(['producto', 'percha'])
            ->where('bodega_id', $bodegaId)
            ->orderBy('producto_id')
            ->get();
    }

    public function getByLocation($productoId, $bodegaId, $perchaId)
    {
        return Inventory::where([
            'producto_id' => $productoId,
            'bodega_id'   => $bodegaId,
            'percha_id'   => $perchaId,
        ])->first();
    }

    public function create(array $data)
    {
        return Inventory::create($data);
    }

    public function update(Inventory $inventory, array $data)
    {
        $inventory->update($data);
        return $inventory;
    }

    public function delete(Inventory $inventory)
    {
        return $inventory->delete();
    }

 

    public function increaseStock(Inventory $inventory, int $cantidad): Inventory
    {
        $inventory->stock_actual += $cantidad;
        $inventory->save();

        return $inventory;
    }

    public function decreaseStock(Inventory $inventory, int $cantidad): Inventory
    {
        $inventory->stock_actual -= $cantidad;
        $inventory->save();

        return $inventory;
    }

   
    public function adjustStock(Inventory $inventory, int $nuevoStock): Inventory
    {
        $inventory->stock_actual = $nuevoStock;
        $inventory->save();

        return $inventory;
    }

    protected function buildTableQuery(
        ?string $search = null,
        ?int $bodegaId = null,
        ?string $categoria = null,
        bool $onlyLow = false
    ): Builder {
        $query = DB::table('inventario as i')
            ->join('products as p', 'p.id', '=', 'i.producto_id')
            ->join('bodegas as b', 'b.id', '=', 'i.bodega_id')
            ->leftJoin('perchas as pe', 'pe.id', '=', 'i.percha_id')
            ->select([
                'i.id',
                'i.producto_id',
                'i.bodega_id',
                'i.percha_id',
                'i.stock_actual',
                'i.stock_reservado',
                'p.nombre as producto_nombre',
                'p.codigo_interno as producto_codigo_interno',
                'p.codigo_barras as producto_codigo_barras',
                'p.categoria as producto_categoria',
                'p.stock_minimo as producto_stock_minimo',
                'b.nombre as bodega_nombre',
                'pe.codigo as percha_codigo',
            ])
            ->orderBy('p.nombre')
            ->orderBy('i.id', 'desc');

        if ($search !== null && $search !== '') {
            $query->where(function (Builder $subQuery) use ($search) {
                $subQuery->where('p.nombre', 'like', '%' . $search . '%')
                    ->orWhere('p.codigo_interno', 'like', '%' . $search . '%')
                    ->orWhere('p.codigo_barras', 'like', '%' . $search . '%');
            });
        }

        if ($bodegaId !== null) {
            $query->where('i.bodega_id', $bodegaId);
        }

        if ($categoria !== null && $categoria !== '') {
            $query->where('p.categoria', $categoria);
        }

        if ($onlyLow) {
            $query->whereColumn('i.stock_actual', '<', 'p.stock_minimo');
        }

        return $query;
    }

    public function paginateForTable(
        ?string $search = null,
        ?int $bodegaId = null,
        ?string $categoria = null,
        bool $onlyLow = false,
        int $page = 1,
        int $perPage = 20
    ) {
        return $this->buildTableQuery($search, $bodegaId, $categoria, $onlyLow)
            ->paginate(
                perPage: $perPage,
                columns: ['*'],
                pageName: 'page',
                page: $page
            );
    }

    public function getFilterOptions(): array
    {
        $bodegas = DB::table('inventario as i')
            ->join('bodegas as b', 'b.id', '=', 'i.bodega_id')
            ->select('b.id', 'b.nombre')
            ->distinct()
            ->orderBy('b.nombre')
            ->get();

        $categorias = DB::table('inventario as i')
            ->join('products as p', 'p.id', '=', 'i.producto_id')
            ->whereNotNull('p.categoria')
            ->where('p.categoria', '!=', '')
            ->select('p.categoria')
            ->distinct()
            ->orderBy('p.categoria')
            ->pluck('categoria')
            ->all();

        return [
            'bodegas' => $bodegas->map(fn ($b) => [
                'id' => (int) $b->id,
                'nombre' => $b->nombre,
            ])->all(),
            'categorias' => $categorias,
        ];
    }
}
