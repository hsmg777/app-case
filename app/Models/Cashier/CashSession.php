<?php

namespace App\Models\Cashier;

use Illuminate\Database\Eloquent\Model;

class CashSession extends Model
{
    protected $table = 'cash_sessions';

    protected $fillable = [
        'caja_id',
        'opened_by',
        'opened_at',
        'opening_amount',
        'closed_by',
        'closed_at',
        'closing_count',
        'expected_amount',
        'declared_amount',
        'difference_amount',
        'status',
        'result',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'declared_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'closing_count' => 'array', 
    ];

    // =========================
    // Relaciones
    // =========================
    public function movements()
    {
        return $this->hasMany(CashMovement::class, 'cash_session_id');
    }

    public function opener()
    {
        return $this->belongsTo(\App\Models\User::class, 'opened_by');
    }

    public function closer()
    {
        return $this->belongsTo(\App\Models\User::class, 'closed_by');
    }

    // =========================
    // Scopes útiles
    // =========================
    public function scopeOpen($query)
    {
        return $query->where('status', 'OPEN');
    }

    public function scopeForCaja($query, $cajaId)
    {
        return $query->where('caja_id', $cajaId);
    }
}
