<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Cashier\CashSession;
use App\Services\Cashier\CashierService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;


class CashierController extends Controller
{
    public function __construct(private CashierService $service)
    {
    }

    // =========================================================
    // Helpers (sin middleware)
    // =========================================================
    private function requireCajaId(Request $request): int
    {
        // caja_id puede venir por querystring, body, hidden input, etc.
        $cajaId = (int)($request->input('caja_id') ?? $request->query('caja_id'));

        if ($cajaId <= 0) {
            throw ValidationException::withMessages([
                'caja_id' => 'Debes indicar el número de caja.',
            ]);
        }

        return $cajaId;
    }

    /**
     * Útil para "bloquear" facturación SIN middleware:
     * En tu controller de facturación llamas a este método o a service->getOpenSessionOrFail().
     */
    public function requireOpenSession(int $cajaId): CashSession
    {
        return $this->service->getOpenSessionOrFail($cajaId);
    }

    // =========================================================
    // APERTURA
    // =========================================================
    public function openView(Request $request)
    {
        $returnTo = $request->query('return_to');
        $bodegaId = $request->query('bodega_id');

        $sessionCajaId = (int) session('caja_id', 0);

        if (!$request->query('force') && $sessionCajaId > 0 && $returnTo) {
            $openSession = $this->service->getOpenSession($sessionCajaId);
            if ($openSession && $this->service->canResumeSession($openSession, (int)auth()->id())) {
                $sep = str_contains($returnTo, '?') ? '&' : '?';
                return redirect($returnTo . $sep . 'caja_id=' . $sessionCajaId);
            }
        }

        $cajaId = (int)($request->query('caja_id') ?? 0);

        $openSession = $cajaId > 0 ? $this->service->getOpenSession($cajaId) : null;
        $isOpen = (bool) $openSession;

        $expected = null;
        $canResume = true;

        if ($openSession) {
            $expected = $this->service->computeExpectedAmount($openSession);
            $canResume = $this->service->canResumeSession($openSession, (int)auth()->id());
        }

        return view('cashier.open', [
            'cajaId' => $cajaId,
            'return_to' => $returnTo,
            'bodega_id' => $bodegaId,
            'openSession' => $openSession,
            'isOpen' => $isOpen,
            'expected' => $expected,
            'canResume' => $canResume,
        ]);
    }



    public function open(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'caja_id' => ['required', 'integer', 'min:1'],
            'opening_amount' => ['nullable', 'numeric', 'min:0'],

            'return_to' => ['nullable', 'string'],
            'bodega_id' => ['nullable', 'integer'],
        ]);

        $validator->after(function ($v) use ($request) {
            $cajaId = (int) $request->input('caja_id');
            if ($cajaId <= 0) return;

            $openSession = $this->service->getOpenSession($cajaId);

            if (!$openSession && $request->input('opening_amount') === null) {
                $v->errors()->add('opening_amount', 'El monto de apertura es obligatorio para abrir una caja nueva.');
            }

            if ($openSession && !$this->service->canResumeSession($openSession, (int)auth()->id())) {
                $v->errors()->add('caja_id', 'Esta caja ya está abierta por otro usuario. No puedes retomar esta sesión.');
            }
        });

        $data = $validator->validate();

        $cajaId = (int) $data['caja_id'];
        $returnTo = $data['return_to'] ?? null;

        $openSession = $this->service->getOpenSession($cajaId);

        if ($openSession) {
            session(['caja_id' => $cajaId]);

            if ($returnTo) {
                $sep = str_contains($returnTo, '?') ? '&' : '?';
                return redirect($returnTo . $sep . 'caja_id=' . $cajaId)
                    ->with('success', 'Caja ya estaba abierta. Sesión retomada.');
            }

            return redirect()->route('dashboard')
                ->with('success', 'Caja ya estaba abierta. Sesión retomada.');
        }

        $this->service->openCash(
            $cajaId,
            (int) auth()->id(),
            (float) $data['opening_amount']
        );

        session(['caja_id' => $cajaId]);

        if ($returnTo) {
            $sep = str_contains($returnTo, '?') ? '&' : '?';
            return redirect($returnTo . $sep . 'caja_id=' . $cajaId)
                ->with('success', 'Caja abierta correctamente.');
        }

        return redirect()->route('dashboard')
            ->with('success', 'Caja abierta correctamente.');
    }


    // =========================================================
    // MOVIMIENTOS (IN / OUT)
    // =========================================================
    public function movement(Request $request)
    {
        $data = $request->validate([
            'caja_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:IN,OUT'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->service->registerMovement(
            (int)$data['caja_id'],
            (int)auth()->id(),
            (string)$data['type'],
            (float)$data['amount'],
            (string)$data['reason']
        );

        $msg = $data['type'] === 'IN' ? 'Ingreso registrado.' : 'Retiro registrado.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }


    // =========================================================
    // CIERRE (VISTA + POST)
    // =========================================================
    public function closeView(Request $request)
    {
        $cajaId = $this->requireCajaId($request);
        $session = $this->service->getOpenSessionOrFail($cajaId);

        // Para mostrar denominaciones en UI
        $denoms = $this->service->denominationMap();

        // Totales actuales (previo al cierre)
        $expected = $this->service->computeExpectedAmount($session);

        // Vista sugerida: resources/views/cashier/close.blade.php
        return view('cashier.close', [
            'cajaId' => $cajaId,
            'session' => $session,
            'denoms' => $denoms,
            'expected' => $expected,
        ]);
    }

    public function close(Request $request)
    {
        $cajaId = $this->requireCajaId($request);

        // Validación simple: cantidades >= 0
        $rules = [
            'caja_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];

        // Construimos reglas dinámicas según keys del mapa
        foreach (array_keys($this->service->denominationMap()) as $key) {
            $rules[$key] = ['nullable', 'integer', 'min:0'];
        }

        $data = $request->validate($rules);

        // Armamos el closing_count con solo las keys de denominaciones
        $closingCount = [];
        foreach (array_keys($this->service->denominationMap()) as $key) {
            $closingCount[$key] = (int)($data[$key] ?? 0);
        }

        $session = $this->service->closeCash(
            $cajaId,
            (int)auth()->id(),
            $closingCount,
            $data['notes'] ?? null
        );

        $message = $session->result === 'MATCH'
            ? 'Caja cerrada y CUADRA.'
            : 'Caja cerrada. Revisa la diferencia en el resumen.';

        return view('cashier.redirect', [
            'redirectTo' => route('cashier.summary', ['id' => $session->id]),
            'message' => $message,
        ]);                                             
    }

    // =========================================================
    // RESUMEN
    // =========================================================
    public function summary($id)
    {
        $session = CashSession::with(['movements.creator', 'opener', 'closer'])->findOrFail($id);

        return view('cashier.summary', [
            'session' => $session,
            'denoms' => $this->service->denominationMap(),
        ]);
    }


    public function printSummary($id)
    {
        $session = CashSession::with([
            'movements.creator',
            'opener',
            'closer',
        ])->findOrFail($id);

        return view('cashier.summary-print', [
            'session' => $session,
        ]);
    }

}
