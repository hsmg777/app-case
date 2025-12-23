<?php

namespace App\Repositories\Sales;

use App\Models\Sales\Sale;
use App\Models\Sales\SaleItem;
use App\Models\Sales\SalePayment;

class SaleRepository
{
    public function createSale(array $data): Sale
    {
        return Sale::create($data);
    }

    public function addItem(Sale $sale, array $itemData): SaleItem
    {
        return $sale->items()->create($itemData);
    }

    public function addPayment(Sale $sale, array $paymentData): SalePayment
    {
        return $sale->payments()->create($paymentData);
    }

    public function updateEstado(Sale $sale, string $estado): Sale
    {
        $sale->estado = $estado;
        $sale->save();

        return $sale;
    }

    public function findById(int $id): ?Sale
    {
        return Sale::with([
            'client.emails',
            'clientEmail',
            'user',
            'bodega',
            'items.producto',
            'payments'
        ])->find($id);
    }


}
