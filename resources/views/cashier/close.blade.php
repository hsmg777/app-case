<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-slate-900 leading-tight">
                Cierre de caja #{{ $cajaId }}
            </h2>

            <button
                onclick="window.location.href='{{ url()->previous() }}'"
                class="text-slate-600 hover:text-slate-900 transition text-sm"
            >
                Atrás
            </button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-6">

            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('cashier.close') }}">
                @csrf
                <input type="hidden" name="caja_id" value="{{ $cajaId }}">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    {{-- Conteo --}}
                    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
                        <h3 class="text-sm font-semibold text-slate-900 mb-4">Arqueo por denominaciones</h3>

                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            @php
                                $labels = [
                                    'c_001' => 'Moneda $0.01',
                                    'c_005' => 'Moneda $0.05',
                                    'c_010' => 'Moneda $0.10',
                                    'c_025' => 'Moneda $0.25',
                                    'c_050' => 'Moneda $0.50',
                                    'c_1'   => 'Moneda $1.00',
                                    'c_2'   => 'Billete $2.00',
                                    'b_5'   => 'Billete $5',
                                    'b_10'  => 'Billete $10',
                                    'b_20'  => 'Billete $20',
                                    'b_50'  => 'Billete $50',
                                    'b_100' => 'Billete $100',
                                ];
                            @endphp

                            @foreach($denoms as $key => $value)
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">
                                        {{ $labels[$key] ?? $key }}
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        step="1"
                                        name="{{ $key }}"
                                        value="{{ old($key, 0) }}"
                                        class="denom-input w-full border-slate-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm h-11"
                                        data-denom-cents="{{ (int) round($value * 100) }}"
                                    >
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">
                                Observación (opcional)
                            </label>
                            <textarea
                                name="notes"
                                rows="2"
                                class="w-full border-slate-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                placeholder="Notas del cierre..."
                            >{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    {{-- Totales --}}
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
                        <h3 class="text-sm font-semibold text-slate-900 mb-4">Resumen</h3>

                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-600">Esperado</span>
                                <span class="font-semibold" id="expectedAmount">${{ number_format($expected, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Declarado</span>
                                <span class="font-semibold text-blue-700" id="declaredAmount">$0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-600">Diferencia</span>
                                <span class="font-semibold" id="diffAmount">$0.00</span>
                            </div>

                            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-[11px] font-semibold text-slate-500 uppercase">Estado</p>
                                <p class="text-lg font-bold" id="statusText">—</p>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="mt-4 w-full inline-flex justify-center items-center px-4 py-3 bg-emerald-600 rounded-2xl text-white font-semibold text-sm shadow hover:bg-emerald-700 transition"
                        >
                            Cerrar caja
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const expected = Number(@json((float)$expected));
            const inputs = Array.from(document.querySelectorAll('.denom-input'));

            function money(n) {
                const v = (Math.round((Number(n) + Number.EPSILON) * 100) / 100);
                return '$' + v.toFixed(2);
            }

            function calc() {
                let totalCents = 0;

                inputs.forEach(i => {
                    const qty = Math.max(0, parseInt(i.value || '0', 10));
                    const denomCents = parseInt(i.dataset.denomCents || '0', 10);
                    totalCents += qty * denomCents;
                });

                const declared = totalCents / 100;
                const diff = Math.round(((declared - expected) + Number.EPSILON) * 100) / 100;

                document.getElementById('declaredAmount').textContent = money(declared);
                document.getElementById('diffAmount').textContent = money(diff);

                const statusEl = document.getElementById('statusText');
                if (diff === 0) {
                    statusEl.textContent = 'CUADRA';
                    statusEl.className = 'text-lg font-bold text-emerald-700';
                } else if (diff < 0) {
                    statusEl.textContent = 'FALTANTE';
                    statusEl.className = 'text-lg font-bold text-rose-700';
                } else {
                    statusEl.textContent = 'SOBRANTE';
                    statusEl.className = 'text-lg font-bold text-amber-700';
                }
            }

            inputs.forEach(i => i.addEventListener('input', calc));
            calc();
        })();
    </script>
</x-app-layout>
