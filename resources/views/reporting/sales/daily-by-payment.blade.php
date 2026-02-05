<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Venta diaria por forma de pago') }}
            </h2>
            <button onclick="window.location.href='{{ route('reporteria.menu') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center space-x-1" title="Regresar">
                <x-heroicon-s-arrow-left class="w-5 h-5" />
                <span>Atras</span>
            </button>
        </div>
    </x-slot>

    @php
        $payLabels = collect($rows ?? [])->map(fn($r) => $r->metodo ?? 'N/D')->values();
        $payTotals = collect($rows ?? [])->map(fn($r) => (float) ($r->total_monto ?? 0))->values();
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="bg-white border border-blue-100 rounded-xl p-4 mb-6 overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-center">
                    <div>
                        <div class="text-sm text-blue-900 font-semibold mb-2">Resumen del dia</div>
                        <div class="space-y-1 text-xs text-blue-800">
                            @if (empty($bodegaId))
                                <div>Total facturado (todas bodegas): <span class="font-semibold">${{ number_format($totalVentasGeneral ?? 0, 2) }}</span></div>
                            @endif
                            <div>Total facturado (bodega seleccionada): <span class="font-semibold">${{ number_format($totalVentas ?? 0, 2) }}</span></div>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm text-blue-900 font-semibold mb-2">Filtro</div>
                        @php
                            $exportParams = [];
                            if (!empty($fecha)) {
                                $exportParams['fecha'] = $fecha;
                            }
                            if (!empty($bodegaId)) {
                                $exportParams['bodega_id'] = $bodegaId;
                            }
                        @endphp
                        <form id="report-daily-sales-form" method="GET" action="{{ route('reporteria.sales.daily.by-payment') }}" class="flex flex-row flex-wrap gap-2 items-center w-full">
                            <input type="date" name="fecha" value="{{ $fecha ?? '' }}"
                                class="border border-blue-100 rounded px-2 py-1 text-xs min-w-[140px]" />
                            <select name="bodega_id" class="border border-blue-100 rounded px-2 py-1 text-xs min-w-[180px]">
                                <option value="">Todas las bodegas</option>
                                @foreach ($bodegas as $b)
                                    <option value="{{ $b->id }}" {{ (string) ($bodegaId ?? '') === (string) $b->id ? 'selected' : '' }}>
                                        {{ $b->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    <div class="flex flex-row gap-2 items-center justify-end">
                        <button type="submit" form="report-daily-sales-form"
                            class="text-xs px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700 shrink-0">Aplicar</button>
                        <a href="{{ route('reporteria.sales.daily.by-payment.export', $exportParams) }}"
                            class="text-xs px-3 py-1 rounded bg-blue-100 text-blue-800 hover:bg-blue-200 text-center whitespace-nowrap shrink-0">
                            Exportar Excel
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm border border-blue-100 rounded-xl overflow-hidden mb-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Bodega</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Ventas</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Total facturado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-100">
                            @forelse ($totalsByBodega as $row)
                                <tr>
                                    <td class="px-4 py-3 text-blue-900">{{ $row->bodega_nombre ?? 'N/D' }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">{{ (int) ($row->ventas ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">
                                        ${{ number_format((float) ($row->total_facturado ?? 0), 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-center text-blue-700" colspan="3">
                                        No hay ventas registradas para este dia.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow-sm border border-blue-100 rounded-xl overflow-hidden mb-6">
                <div class="p-4">
                    <div class="text-sm text-blue-900 font-semibold mb-3">Grafico por forma de pago</div>
                    <div class="h-48">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm border border-blue-100 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Forma de pago</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Pagos</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Ventas</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-100">
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 text-blue-900">
                                        {{ $row->metodo ?? 'N/D' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-blue-900">
                                        {{ (int) ($row->pagos ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-blue-900">
                                        {{ (int) ($row->ventas ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-blue-900">
                                        ${{ number_format((float) ($row->total_monto ?? 0), 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-center text-blue-700" colspan="4">
                                        No hay ventas registradas para este dia.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (($rows ?? collect())->count())
                            <tfoot class="bg-blue-50">
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-blue-900">Total</td>
                                    <td class="px-4 py-3 text-right font-semibold text-blue-900">{{ (int) ($rows->sum('pagos')) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-blue-900">{{ (int) ($rows->sum('ventas')) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-blue-900">
                                        ${{ number_format((float) ($rows->sum('total_monto')), 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const labels = @json($payLabels);
            const totals = @json($payTotals);

            function initChart() {
                if (!window.Chart) return false;
                const ctx = document.getElementById('paymentChart');
                if (!ctx) return true;

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Total por forma de pago',
                            data: totals,
                            backgroundColor: '#1D4ED8'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });

                return true;
            }

            let tries = 0;
            function tryInit() {
                tries += 1;
                if (initChart()) return;
                if (tries < 20) setTimeout(tryInit, 200);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', tryInit);
            } else {
                tryInit();
            }
        })();
    </script>
</x-app-layout>
