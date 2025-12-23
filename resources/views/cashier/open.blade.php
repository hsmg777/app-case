<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-slate-900 leading-tight">
                Apertura de caja
            </h2>

            <button
                onclick="window.location.href='{{ route('dashboard') }}'"
                class="text-slate-600 hover:text-slate-900 transition flex items-center space-x-1 text-sm"
            >
                <span>Atrás</span>
            </button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-6">

            @if(session('error'))
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6">
                <p class="text-sm text-slate-600 mb-4">
                    Para continuar a facturación, primero debes abrir caja.
                </p>

                <form method="POST" action="{{ route('cashier.open') }}" class="space-y-4">
                @csrf

                <input type="hidden" name="return_to" value="{{ $return_to ?? '' }}">
                <input type="hidden" name="bodega_id" value="{{ $bodega_id ?? '' }}">

                <div>
                    <label class="block text-[11px] tracking-wide font-semibold text-slate-500 uppercase mb-1">
                        Número de caja registradora
                    </label>

                    <select
                        id="caja_id_select"
                        name="caja_id"
                        class="w-full border-slate-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm h-11"
                        required
                    >
                        <option value="">Selecciona caja…</option>
                        @for($i=1; $i<=9; $i++)
                            <option value="{{ $i }}" {{ (old('caja_id', $cajaId ?? '') == $i) ? 'selected' : '' }}>
                                Caja #{{ $i }}
                            </option>
                        @endfor
                    </select>

                    @if(!empty($openSession) && !$canResume)
                        <p class="mt-2 text-xs text-rose-600 font-semibold">
                            Esta caja está abierta por otro usuario. No puedes retomar esta sesión.
                        </p>
                    @endif
                </div>

                @if(!empty($openSession))
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        <p class="font-semibold">
                             La Caja #{{ $openSession->caja_id }} ya está abierta.
                        </p>
                        <p class="text-xs text-emerald-700 mt-1">
                            Abierta el {{ optional($openSession->opened_at)->format('d/m/Y H:i') }}.
                        </p>

                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-xs text-emerald-700 font-semibold">Monto esperado actual (resumen):</span>
                            <span class="text-base font-bold text-emerald-800">
                                $ {{ number_format((float)($expected ?? 0), 2) }}
                            </span>
                        </div>
                    </div>

                    <input type="hidden" name="opening_amount" value="{{ number_format((float)($expected ?? 0), 2, '.', '') }}">

                @else
                    <div>
                        <label class="block text-[11px] tracking-wide font-semibold text-slate-500 uppercase mb-1">
                            Monto de apertura
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="opening_amount"
                            value="{{ old('opening_amount', '0.00') }}"
                            class="w-full border-slate-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm h-11"
                            placeholder="0.00"
                            required
                        >
                    </div>
                @endif

                <button
                    type="submit"
                    class="w-full inline-flex justify-center items-center gap-2 px-4 py-3 bg-blue-700 rounded-2xl text-white font-semibold text-sm shadow hover:bg-blue-800 transition
                        {{ (!empty($openSession) && !$canResume) ? 'opacity-50 cursor-not-allowed' : '' }}"
                    {{ (!empty($openSession) && !$canResume) ? 'disabled' : '' }}
                >
                    {{ !empty($openSession) ? 'Continuar con caja' : 'Abrir caja y continuar' }}
                </button>

                <p class="text-[11px] text-slate-400 text-center">
                    Responsable: {{ auth()->user()->name ?? 'Usuario' }}
                </p>
            </form>

            <script>
            (function(){
                const sel = document.getElementById('caja_id_select');
                if (!sel) return;

                sel.addEventListener('change', function(){
                    const caja = this.value || '';
                    const url = new URL(window.location.href);

                    if (caja) url.searchParams.set('caja_id', caja);
                    else url.searchParams.delete('caja_id');

                    // Mantener return_to y bodega_id si existen
                    const returnTo = @json($return_to ?? '');
                    const bodegaId = @json($bodega_id ?? '');

                    if (returnTo) url.searchParams.set('return_to', returnTo);
                    if (bodegaId) url.searchParams.set('bodega_id', bodegaId);

                    // Si estaba force=1, lo mantenemos
                    if (url.searchParams.get('force') === null && {{ request()->query('force') ? 'true' : 'false' }}) {
                        url.searchParams.set('force', '1');
                    }

                    window.location.href = url.toString();
                });
            })();
            </script>

            </div>
        </div>
    </div>
</x-app-layout>
