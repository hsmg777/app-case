<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product\Product;    
use App\Models\Store\Bodega;
use App\Models\Store\Percha;

class Inventory extends Model
{
    protected $table = 'inventario';

    protected $fillable = [
        'producto_id',
        'bodega_id',
        'percha_id',
        'stock_actual',
        'stock_reservado',
    ];

    public function producto()
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function percha()
    {
        return $this->belongsTo(Percha::class, 'percha_id');
    }
}
