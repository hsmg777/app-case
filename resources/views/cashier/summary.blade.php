<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-slate-900 leading-tight">
                Resumen de caja #{{ $session->caja_id }}
            </h2>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    id="btnPrintCashSummary"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold shadow hover:bg-black transition"
                >
                    Imprimir resumen
                </button>

                <button
                    onclick="window.location.href='{{ route('dashboard') }}'"
                    class="text-slate-600 hover:text-slate-900 transition text-sm"
                >
                    Ir al dashboard
                </button>
            </div>
        </div>
    </x-slot>

    @php
        $resultLabel = $session->result === 'MATCH'
            ? 'CUADRA'
            : ($session->result === 'SHORT'
                ? 'FALTANTE'
                : ($session->result === 'OVER'
                    ? 'SOBRANTE'
                    : $session->result
                )
            );
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-6">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-[11px] text-slate-500 uppercase font-semibold">Apertura</p>
                        <p class="font-semibold">{{ $session->opened_at?->format('d/m/Y H:i') }}</p>
                        <p class="text-slate-600">Monto: ${{ number_format((float)$session->opening_amount, 2) }}</p>
                        <p class="text-slate-600">Por: {{ $session->opener?->name }}</p>
                    </div>

                    <div>
                        <p class="text-[11px] text-slate-500 uppercase font-semibold">Cierre</p>
                        <p class="font-semibold">{{ $session->closed_at?->format('d/m/Y H:i') }}</p>
                        <p class="text-slate-600">Por: {{ $session->closer?->name }}</p>

                        {{-- ✅ traducido --}}
                        <p class="text-slate-600">
                            Resultado: <span class="font-bold">{{ $resultLabel }}</span>
                        </p>
                    </div>

                    <div>
                        <p class="text-[11px] text-slate-500 uppercase font-semibold">Totales</p>
                        <p class="text-slate-600">Esperado: <span class="font-semibold">${{ number_format((float)$session->expected_amount, 2) }}</span></p>
                        <p class="text-slate-600">Declarado: <span class="font-semibold">${{ number_format((float)$session->declared_amount, 2) }}</span></p>
                        <p class="text-slate-600">Diferencia: <span class="font-semibold">${{ number_format((float)$session->difference_amount, 2) }}</span></p>
                    </div>
                </div>

                <hr class="my-5">

                <h3 class="text-sm font-semibold text-slate-900 mb-3">Movimientos (Ingresos / Retiros)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="text-left px-3 py-2 text-[11px] uppercase text-slate-500">Fecha</th>
                                <th class="text-left px-3 py-2 text-[11px] uppercase text-slate-500">Tipo</th>
                                <th class="text-right px-3 py-2 text-[11px] uppercase text-slate-500">Monto</th>
                                <th class="text-left px-3 py-2 text-[11px] uppercase text-slate-500">Motivo</th>
                                <th class="text-left px-3 py-2 text-[11px] uppercase text-slate-500">Usuario</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @forelse($session->movements as $m)
                                @php
                                    $tipoLabel = $m->type === 'IN'
                                        ? 'Ingreso'
                                        : ($m->type === 'OUT' ? 'Retiro' : $m->type);
                                @endphp

                                <tr>
                                    <td class="px-3 py-2">{{ $m->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2 font-semibold">{{ $tipoLabel }}</td>
                                    <td class="px-3 py-2 text-right">${{ number_format((float)$m->amount, 2) }}</td>
                                    <td class="px-3 py-2">{{ $m->reason }}</td>
                                    <td class="px-3 py-2">{{ $m->creator?->name }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-slate-400">
                                        Sin movimientos registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($session->notes)
                    <div class="mt-4 text-sm text-slate-600">
                        <p class="text-[11px] uppercase font-semibold text-slate-500">Notas</p>
                        <p>{{ $session->notes }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <iframe
        id="cashSummaryPrintFrame"
        style="position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;"
    ></iframe>

    <script>
    (function () {
        const btn = document.getElementById('btnPrintCashSummary');
        const frame = document.getElementById('cashSummaryPrintFrame');

        if (!btn || !frame) return;

        btn.addEventListener('click', () => {
            const url = @json(route('cashier.summary.print', ['id' => $session->id]));
            frame.src = url + (url.includes('?') ? '&' : '?') + '_t=' + Date.now();
        });

        window.addEventListener('message', (e) => {
            if (e?.data?.type === 'cash-summary-printed') {
                frame.src = 'about:blank';
            }
        });
    })();
    </script>

</x-app-layout>
