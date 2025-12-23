<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Clients\Client;
use App\Models\User;
use App\Models\Store\Bodega;

class Sale extends Model
{
    use HasFactory;

    protected $table = 'sales';

    protected $fillable = [
        'client_id',
        'client_email_id',
        'email_destino', 
        'user_id',
        'bodega_id',
        'fecha_venta',
        'tipo_documento',
        'num_factura',
        'subtotal',
        'descuento',
        'impuesto',
        'iva',
        'total',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'subtotal'    => 'decimal:2',
        'descuento'   => 'decimal:2',
        'impuesto'    => 'decimal:2',
        'iva'         => 'decimal:2',
        'total'       => 'decimal:2',
    ];

    /* ==========================
       RELACIONES
    ========================== */

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class, 'sale_id');
    }

    public function electronicInvoice()
    {
        return $this->hasOne(\App\Models\Sri\ElectronicInvoice::class, 'sale_id');
    }

    public function clientEmail()
    {
        return $this->belongsTo(\App\Models\Clients\ClientEmail::class, 'client_email_id');
    }


    /* ==========================
       HELPERS
    ========================== */

    public function getTotalPagadoAttribute()
    {
        return $this->payments->sum('monto');
    }

    public function getSaldoPendienteAttribute()
    {
        return $this->total - $this->total_pagado;
    }
}
