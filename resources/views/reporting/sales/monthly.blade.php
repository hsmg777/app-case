<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Reporte mensual de ventas') }}
            </h2>
            <button onclick="window.location.href='{{ route('reporteria.menu') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center space-x-1" title="Regresar">
                <x-heroicon-s-arrow-left class="w-5 h-5" />
                <span>Atras</span>
            </button>
        </div>
    </x-slot>

    @php
        $monthlyLabels = collect($rowsChart)->map(function ($row) {
            return $row->mes ? \Illuminate\Support\Carbon::parse($row->mes)->locale('es')->translatedFormat('F Y') : 'N/D';
        })->values();
        $monthlyTotals = collect($rowsChart)->map(fn($row) => (float) ($row->total ?? 0))->values();
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 space-y-6">
            <div class="bg-white border border-blue-100 rounded-xl p-4 overflow-hidden">
                <div class="grid grid-row-1 lg:grid-rows-3 items-center">
                    <div>
                        <div class="text-lg text-blue-900 font-semibold mb-2">Mensual</div>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm text-blue-900 font-semibold mb-2">Filtro por mes</div>
                        @php
                            $exportParams = [];
                            if (!empty($mes)) {
                                $exportParams['mes'] = $mes;
                            }
                        @endphp
                        <form method="GET" action="{{ route('reporteria.sales.monthly') }}" class="flex flex-wrap gap-2 items-center">
                            <select name="mes" class="border border-blue-100 rounded px-2 py-1 text-xs min-w-[220px]">
                                <option value="">Todos los meses</option>
                                @foreach ($months as $m)
                                    <option value="{{ $m['value'] }}" {{ ($mes ?? '') === $m['value'] ? 'selected' : '' }}>
                                        {{ $m['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="text-xs px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700 shrink-0">Aplicar</button>
                            <a href="{{ route('reporteria.sales.monthly.export', $exportParams) }}"
                                class="text-xs px-3 py-1 rounded bg-blue-100 text-blue-800 hover:bg-blue-200 text-center whitespace-nowrap shrink-0">
                                Exportar Excel
                            </a>
                        </form>
                    </div>
                    <div class="text-xs text-blue-800">
                        Comprobantes: solo FACTURA. Sub 0 y Sub 15 calculados desde IVA por producto.
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm border border-blue-100 rounded-xl overflow-hidden">
                <div class="p-4">
                    <div class="text-sm text-blue-900 font-semibold mb-3">Frecuencia mensual</div>
                    <div class="h-40">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Mes</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Comprobantes</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Sub 0</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Sub 15</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">IVA 15</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-100">
                            @forelse ($rowsTable as $row)
                                @php
                                    $mesLabel = $row->mes ? \Illuminate\Support\Carbon::parse($row->mes)->locale('es')->translatedFormat('F Y') : 'N/D';
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-blue-900">{{ $mesLabel }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">{{ (int) ($row->comprobantes ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">${{ number_format((float) ($row->sub0 ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">${{ number_format((float) ($row->sub15 ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">${{ number_format((float) ($row->iva ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">${{ number_format((float) ($row->total ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-center text-blue-700" colspan="6">
                                        No hay ventas registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">
                    {{ $rowsTable->links() }}
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const monthlyLabels = @json($monthlyLabels);
            const monthlyTotals = @json($monthlyTotals);

            function initCharts() {
                if (!window.Chart) return false;

                const monthlyCtx = document.getElementById('monthlyChart');

                if (monthlyCtx) {
                    new Chart(monthlyCtx, {
                        type: 'line',
                        data: {
                            labels: monthlyLabels,
                            datasets: [{
                                label: 'Total por mes',
                                data: monthlyTotals,
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
