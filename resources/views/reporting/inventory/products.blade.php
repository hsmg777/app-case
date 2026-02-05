<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Reporte de inventario por producto y bodega') }}
            </h2>
            <button onclick="window.location.href='{{ route('reporteria.menu') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center space-x-1" title="Regresar">
                <x-heroicon-s-arrow-left class="w-5 h-5" />
                <span>Atras</span>
            </button>
        </div>
    </x-slot>

    @php
        $chartLabels = collect($chartRows ?? [])->map(function ($r) {
            $prod = $r->producto_nombre ?? 'N/D';
            $bod = $r->bodega_nombre ?? 'N/D';
            return "{$prod} ({$bod})";
        })->values();
        $chartStocks = collect($chartRows ?? [])->map(fn($r) => (int) ($r->stock_actual ?? 0))->values();
        $chartMins = collect($chartRows ?? [])->map(fn($r) => (int) ($r->stock_minimo ?? 0))->values();
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 space-y-6">
            <div class="bg-white border border-blue-100 rounded-xl p-4 overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-center">
                    <div>
                        <div class="text-sm text-blue-900 font-semibold mb-2">Filtros</div>
                        <div class="text-xs text-blue-800">
                            Orden: primero stock bajo y luego stock ascendente.
                        </div>
                    </div>
                    <div class="min-w-0">
                        @php
                            $exportParams = [];
                            if (!empty($bodegaId)) {
                                $exportParams['bodega_id'] = $bodegaId;
                            }
                            if (!empty($categoria)) {
                                $exportParams['categoria'] = $categoria;
                            }
                            if (!empty($q)) {
                                $exportParams['q'] = $q;
                            }
                        @endphp
                        <form method="GET" action="{{ route('reporteria.inventory.products') }}" class="flex flex-row gap-2 items-center">
                            <select name="bodega_id" class="border border-blue-100 rounded px-2 py-1 text-xs min-w-[160px]">
                                <option value="">Todas las bodegas</option>
                                @foreach ($bodegas as $b)
                                    <option value="{{ $b->id }}" {{ (string) ($bodegaId ?? '') === (string) $b->id ? 'selected' : '' }}>
                                        {{ $b->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <select name="categoria" class="border border-blue-100 rounded px-2 py-1 text-xs min-w-[160px]">
                                <option value="">Todas las categorias</option>
                                @foreach ($categorias as $cat)
                                    <option value="{{ $cat }}" {{ ($categoria ?? '') === $cat ? 'selected' : '' }}>
                                        {{ $cat }}
                                    </option>
                                @endforeach
                            </select>
                            <input name="q" value="{{ $q ?? '' }}" placeholder="Buscar producto"
                                class="border border-blue-100 rounded px-2 py-1 text-xs min-w-[180px]" />
                            <button type="submit" class="text-xs px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700 shrink-0">Aplicar</button>
                            <a href="{{ route('reporteria.inventory.products.export', $exportParams) }}"
                                class="text-xs px-3 py-1 rounded bg-blue-100 text-blue-800 hover:bg-blue-200 text-center whitespace-nowrap shrink-0">
                                Exportar Excel
                            </a>
                        </form>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm border border-blue-100 rounded-xl overflow-hidden">
                <div class="p-4">
                    <div class="text-sm text-blue-900 font-semibold mb-3">Stock actual (Top 10 mas bajos)</div>
                    <div class="h-40">
                        <canvas id="inventoryChart"></canvas>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Producto</th>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Categoria</th>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Bodega</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Stock actual</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-900">Stock minimo</th>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-100">
                            @forelse ($rows as $row)
                                @php
                                    $isLow = !empty($row->is_low);
                                    $isNegative = !empty($row->is_negative);
                                    $rowClass = $isNegative ? 'bg-red-100' : ($isLow ? 'bg-orange-50' : '');
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td class="px-4 py-3 text-blue-900">{{ $row->producto_nombre ?? 'N/D' }}</td>
                                    <td class="px-4 py-3 text-blue-900">{{ $row->categoria ?? 'N/D' }}</td>
                                    <td class="px-4 py-3 text-blue-900">{{ $row->bodega_nombre ?? 'N/D' }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">{{ (int) ($row->stock_actual ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right text-blue-900">{{ (int) ($row->stock_minimo ?? 0) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($isNegative)
                                            <span class="px-2 py-1 text-xs rounded bg-red-200 text-red-900">EN CONTRA</span>
                                        @elseif ($isLow)
                                            <span class="px-2 py-1 text-xs rounded bg-orange-100 text-orange-800">BAJO</span>
                                        @else
                                            <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800">OK</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-center text-blue-700" colspan="6">
                                        No hay productos para los filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">
                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const labels = @json($chartLabels);
            const stocks = @json($chartStocks);
            const mins = @json($chartMins);

            function initChart() {
                if (!window.Chart) return false;
                const ctx = document.getElementById('inventoryChart');
                if (!ctx) return true;

            const colors = stocks.map((s, i) => {
                if (s < 0) return '#DC2626';
                if (s < (mins[i] || 0)) return '#F97316';
                return '#1D4ED8';
            });

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Stock actual',
                            data: stocks,
                            backgroundColor: colors,
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
