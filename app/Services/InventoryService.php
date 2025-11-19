<?php

namespace App\Services;

use App\Models\Inventory;

class InventoryService
{
    public function getAll()
    {
        return Inventory::with(['producto', 'bodega', 'percha'])
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getById($id)
    {
        return Inventory::with(['producto', 'bodega', 'percha'])
            ->findOrFail($id);
    }

    public function getByProduct($productoId)
    {
        return Inventory::with(['producto', 'bodega', 'percha'])
            ->where('producto_id', $productoId)
            ->get();
    }

    public function getByBodega($bodegaId)
    {
        return Inventory::with(['producto', 'bodega', 'percha'])
            ->where('bodega_id', $bodegaId)
            ->get();
    }

    public function create($data)
    {
        return Inventory::create($data);
    }

    public function update($id, $data)
    {
        $inv = Inventory::findOrFail($id);
        $inv->update($data);
        return $inv;
    }

    public function delete($id)
    {
        Inventory::findOrFail($id)->delete();
        return true;
    }

    public function increaseStock($productoId, $bodegaId, $perchaId, $cantidad)
    {
        $inv = Inventory::where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->where('percha_id', $perchaId)
            ->firstOrFail();

        $inv->stock_actual += $cantidad;
        $inv->save();

        return $inv;
    }

    public function decreaseStock($productoId, $bodegaId, $perchaId, $cantidad)
    {
        $inv = Inventory::where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->where('percha_id', $perchaId)
            ->firstOrFail();

        if ($inv->stock_actual < $cantidad) {
            throw new \Exception("Stock insuficiente");
        }

        $inv->stock_actual -= $cantidad;
        $inv->save();

        return $inv;
    }
}
