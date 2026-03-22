<?php

namespace App\Services\Product;

use App\Repositories\Product\ProductPriceRepository;
use Illuminate\Support\Facades\DB;

class ProductPriceService
{
    protected $repo;

    public function __construct(ProductPriceRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getAll()
    {
        return $this->repo->all();
    }

    public function getById($id)
    {
        return $this->repo->find($id);
    }

    public function getByProduct($productoId)
    {
        return $this->repo->findByProduct($productoId);
    }


    public function create(array $data)
    {
        if (empty($data['moneda'])) {
            $data['moneda'] = 'USD';
        }

        return $this->repo->create($data);
    }

    public function update($id, array $data)
    {
        $price = $this->repo->find($id);

        if (empty($data['moneda'])) {
            $data['moneda'] = $price->moneda ?? 'USD';
        }

        return $this->repo->update($price, $data);
    }

    public function bulkUpsert(array $data): array
    {
        $productIds = collect($data['producto_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $pricePayload = [
            'precio_unitario' => $data['precio_unitario'],
            'moneda' => $data['moneda'] ?: 'USD',
            'precio_por_cantidad' => $data['precio_por_cantidad'] ?? null,
            'cantidad_min' => $data['cantidad_min'] ?? null,
            'cantidad_max' => $data['cantidad_max'] ?? null,
            'precio_por_caja' => $data['precio_por_caja'] ?? null,
            'unidades_por_caja' => $data['unidades_por_caja'] ?? null,
        ];

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($productIds, $pricePayload, &$created, &$updated) {
            foreach ($productIds as $productId) {
                $existing = $this->repo->findByProduct($productId);

                if ($existing) {
                    $this->repo->update($existing, $pricePayload);
                    $updated++;
                    continue;
                }

                $this->repo->create([
                    'producto_id' => $productId,
                    ...$pricePayload,
                ]);
                $created++;
            }
        });

        return [
            'message' => 'Precios actualizados masivamente.',
            'products_count' => count($productIds),
            'created_count' => $created,
            'updated_count' => $updated,
        ];
    }

    public function delete($id)
    {
        $price = $this->repo->find($id);
        return $this->repo->delete($price);
    }
}
