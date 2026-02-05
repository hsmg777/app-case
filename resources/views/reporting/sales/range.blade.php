<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Ventas por rango de fechas') }}
            </h2>
            <button onclick="window.location.href='{{ route('reporteria.menu') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center space-x-1" title="Regresar">
                <x-heroicon-s-arrow-left class="w-5 h-5" />
                <span>Atras</span>
            </button>
        </div>
    </x-slot>

    @php
        $rangeLabels = collect($rangeFrequency ?? [])->map(function ($row) {
            return $row->dia ? \Illuminate\Support\Carbon::parse($row->dia)->format('Y-m-d') : 'N/D';
        })->values();
        $rangeTotals = collect($rangeFrequency ?? [])->map(fn($row) => (float) ($row->total ?? 0))->values();
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 space-y-6">
            <div class="bg-white border border-blue-100 rounded-xl p-4 overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-center">
                    <div>
                        <div class="text-sm text-blue-900 font-semibold mb-2">Rango</div>
                        <div class="text-xs text-blue-800">
                            Desde: <span class="font-semibold">{{ $desde }}</span> | Hasta: <span class="font-semibold">{{ $hasta }}</span>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm text-blue-900 font-semibold mb-2">Filtro por rango</div>
                        @php
                            $exportParams = [];
                            if (!empty($desde)) {
                                $exportParams['desde'] = $desde;
                            }
                            if (!empty($hasta)) {
                                $exportParams['hasta'] = $hasta;
                            }
                        @endphp
                        <form method="GET" action="{{ route('reporteria.sales.range') }}" class="flex flex-row gap-2 items-center">
                            <input type="date" name="desde" value="{{ $desde ?? '' }}"
                                class="border border-blue-100 rounded px-2 py-1 text-xs min-w-[140px]" />
                            <input type="date" name="hasta" value="{{ $hasta ?? '' }}"
                                class="border border-blue-100 rounded px-2 py-1 text-xs min-w-[140px]" />
                            <button type="submit" class="text-xs px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700 shrink-0">Aplicar</button>
                            <a href="{{ route('reporteria.sales.range.export', $exportParams) }}"
                                class="text-xs px-3 py-1 rounded bg-blue-100 text-blue-800 hover:bg-blue-200 text-center whitespace-nowrap shrink-0">
                                Exportar Excel
                            </a>
                        </form>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm border border-blue-100 rounded-xl overflow-hidden">
                <div class="p-4">
                    <div class="text-sm text-blue-900 font-semibold mb-3">Frecuencia por dia</div>
                    <div class="h-40">
                        <canvas id="rangeChart"></canvas>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Fecha</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Comprobantes</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Sub 0</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Sub 15</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">IVA 15</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-100">
                            <tr>
                                <td class="px-4 py-3 text-blue-900">{{ $desde }} a {{ $hasta }}</td>
                                <td class="px-4 py-3 text-right text-blue-900">{{ (int) ($rangeSummary->comprobantes ?? 0) }}</td>
                                <td class="px-4 py-3 text-right text-blue-900">${{ number_format((float) ($rangeSummary->sub0 ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right text-blue-900">${{ number_format((float) ($rangeSummary->sub15 ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right text-blue-900">${{ number_format((float) ($rangeSummary->iva ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right text-blue-900">${{ number_format((float) ($rangeSummary->total ?? 0), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow-sm border border-blue-100 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Dia</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Comprobantes</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-100">
                            @forelse ($rangeTable as $row)
                                <tr>
                                    <td class="px-4 py-3 text-blue-900">{{ $row->dia }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">{{ (int) ($row->comprobantes ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">${{ number_format((float) ($row->total ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-center text-blue-700" colspan="3">
                                        No hay ventas registradas en este rango.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">
                    {{ $rangeTable->withQueryString()->links() }}
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const rangeLabels = @json($rangeLabels);
            const rangeTotals = @json($rangeTotals);

            function initCharts() {
                if (!window.Chart) return false;

                const rangeCtx = document.getElementById('rangeChart');
                if (rangeCtx) {
                    new Chart(rangeCtx, {
                        type: 'line',
                        data: {
                            labels: rangeLabels,
                            datasets: [{
                                label: 'Total por dia',
                                data: rangeTotals,
                                borderColor: '#1D4ED8',
                                backgroundColor: 'rgba(29, 78, 216, 0.12)',
                                fill: true,
                                tension: 0.3,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                }

                return true;
            }

            let tries = 0;
            function tryInit() {
                tries += 1;
                if (initCharts()) return;
                if (tries < 20) {
                    setTimeout(tryInit, 200);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', tryInit);
            } else {
                tryInit();
            }
        })();
    </script>
</x-app-layout>
