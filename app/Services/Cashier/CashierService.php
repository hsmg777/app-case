<?php

namespace App\Services\Cashier;

use App\Models\Cashier\CashSession;
use App\Repositories\Cashier\CashierRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashierService
{
    public function __construct(private CashierRepository $repo)
    {
    }

    /**
     * Mapa fijo de denominaciones (sin tabla).
     * Keys "seguras" para JSON (evitas usar 0.01 como key).
     */
    public function denominationMap(): array
    {
        return [
            'c_001' => 0.01,
            'c_005' => 0.05,
            'c_010' => 0.10,
            'c_025' => 0.25,
            'c_050' => 0.50,
            'c_1'   => 1.00,
            'c_2'   => 2.00,
            'b_5'   => 5.00,
            'b_10'  => 10.00,
            'b_20'  => 20.00,
            'b_50'  => 50.00,
            'b_100' => 100.00,
        ];
    }

    // =========================
    // Apertura
    // =========================
    public function openCash(int $cajaId, int $userId, float $openingAmount): CashSession
    {
        if ($cajaId <= 0) {
            throw ValidationException::withMessages(['caja_id' => 'Número de caja inválido.']);
        }
        if ($openingAmount < 0) {
            throw ValidationException::withMessages(['opening_amount' => 'Monto de apertura inválido.']);
        }

        if ($this->repo->hasOpenSession($cajaId)) {
            throw ValidationException::withMessages(['caja_id' => 'Esta caja ya tiene una sesión abierta.']);
        }

        return $this->repo->createSession([
            'caja_id' => $cajaId,
            'opened_by' => $userId,
            'opened_at' => now(),
            'opening_amount' => $openingAmount,
            'status' => 'OPEN',
        ]);
    }

    public function getOpenSessionOrFail(int $cajaId): CashSession
    {
        $session = $this->repo->getOpenSession($cajaId);

        if (!$session) {
            throw ValidationException::withMessages([
                'caja_id' => 'No hay caja abierta para esta caja.',
            ]);
        }

        return $session;
    }

    // =========================
    // Movimientos (Ingreso / Retiro)
    // =========================
    public function registerMovement(
        int $cajaId,
        int $userId,
        string $type,
        float $amount,
        string $reason
    ) {
        $type = strtoupper(trim($type));
        if (!in_array($type, ['IN', 'OUT'], true)) {
            throw ValidationException::withMessages(['type' => 'Tipo inválido. Use IN u OUT.']);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'El monto debe ser mayor a 0.']);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'El motivo es obligatorio.']);
        }

        $session = $this->getOpenSessionOrFail($cajaId);

        return $this->repo->createMovement([
            'cash_session_id' => $session->id,
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'created_by' => $userId,
        ]);
    }

    // =========================
    // Cierre (Arqueo)
    // =========================
    public function computeDeclaredAmount(array $closingCount): float
    {
        $map = $this->denominationMap();

        // centavos para evitar errores float
        $totalCents = 0;

        foreach ($map as $key => $value) {
            $qty = (int)($closingCount[$key] ?? 0);
            if ($qty < 0) $qty = 0;

            $denomCents = (int) round($value * 100);
            $totalCents += $qty * $denomCents;
        }

        return round($totalCents / 100, 2);
    }

    /**
     * Expected por ahora: apertura + IN - OUT.
     * Luego, cuando conectemos facturación, sumamos ventas en efectivo.
     */
    public function computeExpectedAmount(CashSession $session): float
    {
        $opening = (float)$session->opening_amount;
        $in = $this->repo->sumMovements($session->id, 'IN');
        $out = $this->repo->sumMovements($session->id, 'OUT');

        return round($opening + $in - $out, 2);
    }

    public function closeCash(
        int $cajaId,
        int $userId,
        array $closingCount,
        ?string $notes = null
    ): CashSession {
        return DB::transaction(function () use ($cajaId, $userId, $closingCount, $notes) {
            $session = $this->getOpenSessionOrFail($cajaId);

            $declared = $this->computeDeclaredAmount($closingCount);
            $expected = $this->computeExpectedAmount($session);

            $diff = round($declared - $expected, 2);
            $result = $diff == 0.0 ? 'MATCH' : ($diff < 0 ? 'SHORT' : 'OVER');

            return $this->repo->updateSession($session, [
                'closed_by' => $userId,
                'closed_at' => now(),
                'closing_count' => $closingCount,
                'expected_amount' => $expected,
                'declared_amount' => $declared,
                'difference_amount' => $diff,
                'status' => 'CLOSED',
                'result' => $result,
                'notes' => $notes,
            ]);
        });
    }
    
      public function getOpenSession(int $cajaId): ?CashSession
    {
        return $this->repo->getOpenSession($cajaId);
    }

    
    public function canResumeSession(CashSession $session, int $userId): bool
    {
        return (int)$session->opened_by === (int)$userId;
    }

    public function registerSaleIncome(
        int $cajaId,
        int $userId,
        int $saleId,
        ?string $numFactura,
        float $amount,
        string $metodo
    ): void {
        $m = strtoupper(trim($metodo));

        $isCash = in_array($m, ['EFECTIVO', 'CASH'], true);
        if (!$isCash) return;

        $session = $this->getOpenSessionOrFail($cajaId);

        if ($this->repo->movementExists($session->id, 'SALE', $saleId)) {
            return;
        }

        $reason = $numFactura
            ? "VENTA EFECTIVO #{$numFactura}"
            : "VENTA EFECTIVO (ID {$saleId})";

        $this->repo->createMovement([
            'cash_session_id' => $session->id,
            'type' => 'IN',
            'amount' => $amount,
            'reason' => $reason,
            'created_by' => $userId,
            'ref_type' => 'SALE',
            'ref_id' => $saleId,
        ]);
    }

}
