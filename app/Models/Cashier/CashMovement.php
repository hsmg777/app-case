<?php

namespace App\Models\Cashier;

use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    protected $table = 'cash_movements';

    protected $fillable = [
        'cash_session_id',
        'type',        // IN / OUT
        'amount',
        'reason',
        'created_by',
        'ref_type',   
        'ref_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // =========================
    // Relaciones
    // =========================
    public function session()
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // =========================
    // Scopes útiles
    // =========================
    public function scopeIn($query)
    {
        return $query->where('type', 'IN');
    }

    public function scopeOut($query)
    {
        return $query->where('type', 'OUT');
    }
}
