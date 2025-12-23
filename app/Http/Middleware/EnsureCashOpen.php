<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Cashier\CashSession;

class EnsureCashOpen
{
    public function handle(Request $request, Closure $next)
    {
        $cajaId = $request->input('caja_id') ?? $request->get('caja_id');

        if (!$cajaId) {
            return redirect()->route('cashier.open.view')
                ->with('error', 'Debes seleccionar el número de caja.');
        }

        $openSession = CashSession::where('caja_id', $cajaId)
            ->where('status', 'OPEN')
            ->latest('opened_at')
            ->first();

        if (!$openSession) {
            return redirect()->route('cashier.open.view', ['caja_id' => $cajaId]);
        }

        $request->attributes->set('cash_session_id', $openSession->id);

        return $next($request);
    }
}
