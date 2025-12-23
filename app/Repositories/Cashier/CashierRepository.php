<?php

namespace App\Repositories\Cashier;

use App\Models\Cashier\CashSession;
use App\Models\Cashier\CashMovement;

class CashierRepository
{
    public function hasOpenSession(int $cajaId): bool
    {
        return CashSession::where('caja_id', $cajaId)->where('status', 'OPEN')->exists();
    }

    public function getOpenSession(int $cajaId): ?CashSession
    {
        return CashSession::where('caja_id', $cajaId)->where('status', 'OPEN')->first();
    }

    public function createSession(array $data): CashSession
    {
        return CashSession::create($data);
    }

    public function updateSession(CashSession $session, array $data): CashSession
    {
        $session->fill($data);
        $session->save();
        return $session->fresh();
    }

    public function createMovement(array $data): CashMovement
    {
        return CashMovement::create($data);
    }

    public function sumMovements(int $sessionId, string $type): float
    {
        return (float) CashMovement::where('cash_session_id', $sessionId)
            ->where('type', $type)
            ->sum('amount');
    }

    public function movementExists(int $sessionId, string $refType, int $refId): bool
    {
        return CashMovement::where('cash_session_id', $sessionId)
            ->where('ref_type', $refType)
            ->where('ref_id', $refId)
            ->exists();
    }
}
