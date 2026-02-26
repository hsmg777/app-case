<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Estados de facturas') }}
            </h2>
            <button onclick="window.location.href='{{ route('reporteria.menu') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center space-x-1" title="Regresar">
                <x-heroicon-s-arrow-left class="w-5 h-5" />
                <span>Atras</span>
            </button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="bg-white border border-blue-100 rounded-xl p-4 mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div>
                        <div class="text-sm text-blue-900 font-semibold mb-2">Leyenda de estados</div>
                        <div class="space-y-1 text-xs text-blue-800">
                            <div><span class="px-2 py-0.5 rounded bg-green-100 text-green-800">AUTORIZADO</span> Comprobante aprobado por SRI.</div>
                            <div><span class="px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">EN_PROCESO</span> SRI procesando (puede tardar).</div>
                            <div><span class="px-2 py-0.5 rounded bg-red-100 text-red-800">RECHAZADO</span> Comprobante no autorizado.</div>
                            <div><span class="px-2 py-0.5 rounded bg-orange-100 text-orange-800">PENDIENTE_REVISION</span> Supera tiempo recomendado; revisar manualmente.</div>
                        </div>
                        <div class="text-xs text-blue-800 mt-2">
                            Revision manual: 1) Consultar estado, 2) Verificar en portal SRI, 3) En pruebas reprocesar si sigue pendiente; en produccion escalar.
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-blue-900 font-semibold mb-2">Boton Consultar</div>
                        <div class="text-xs text-blue-800">
                            Actualiza el estado consultando al SRI y muestra el detalle en este modal.
                            Solo admin puede usarlo.
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-blue-900 font-semibold mb-2">Filtros</div>
                        <form method="GET" action="{{ route('reporteria.invoices.statuses') }}" class="space-y-2">
                            <div class="flex flex-col sm:flex-row gap-2">
                                <select name="estado" class="border border-blue-100 rounded px-2 py-1 text-xs">
                                    <option value="" {{ $estado === '' ? 'selected' : '' }}>Todos</option>
                                    <option value="AUTORIZADO" {{ $estado === 'AUTORIZADO' ? 'selected' : '' }}>AUTORIZADO</option>
                                    <option value="EN_PROCESO" {{ $estado === 'EN_PROCESO' ? 'selected' : '' }}>EN_PROCESO</option>
                                    <option value="RECHAZADO" {{ $estado === 'RECHAZADO' ? 'selected' : '' }}>RECHAZADO</option>
                                    <option value="PENDIENTE_REVISION" {{ $estado === 'PENDIENTE_REVISION' ? 'selected' : '' }}>PENDIENTE_REVISION</option>
                                </select>
                                <input name="q" value="{{ $q ?? '' }}" placeholder="Buscar venta, factura o clave"
                                    class="border border-blue-100 rounded px-2 py-1 text-xs flex-1" />
                                <button type="submit" class="text-xs px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700">Aplicar</button>
                            </div>
                            <div class="flex justify-end">
                                <a href="{{ route('reporteria.invoices.statuses.xml-zip', ['estado' => $estado, 'q' => $q]) }}"
                                    class="inline-block text-xs px-3 py-1 rounded bg-emerald-600 text-white hover:bg-emerald-700">
                                    Descargar XML (ZIP)
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm border border-blue-100 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Invoice</th>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Venta</th>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Factura</th>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Clave acceso</th>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Estado</th>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Actualizado</th>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Tiempo</th>
                                <th class="px-4 py-3 text-left font-semibold text-blue-900">Accion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-100">
                            @forelse ($invoices as $inv)
                                @php
                                    $estadoRow = strtoupper((string) ($inv->estado_sri ?? ''));
                                    $sale = $inv->sale;
                                    $pendingReview = !empty($inv->pendiente_revision);
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-blue-900">#{{ $inv->id }}</td>
                                    <td class="px-4 py-3">
                                        {{ $sale?->id ? '#'.$sale->id : 'N/D' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $sale?->num_factura ?? 'N/D' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-blue-900">
                                        <span class="break-all" title="{{ $inv->clave_acceso }}">
                                            {{ $inv->clave_acceso }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($estadoRow === 'AUTORIZADO')
                                            <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-800">AUTORIZADO</span>
                                        @elseif ($estadoRow === 'RECHAZADO')
                                            <span class="px-2 py-1 text-xs rounded bg-red-100 text-red-800">RECHAZADO</span>
                                        @elseif ($pendingReview)
                                            <span class="px-2 py-1 text-xs rounded bg-orange-100 text-orange-800">PENDIENTE_REVISION</span>
                                        @else
                                            <span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800">{{ $estadoRow ?: 'PENDIENTE' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-blue-900">
                                        {{ $inv->updated_at?->format('Y-m-d H:i') ?? 'N/D' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-blue-900">
                                        @if ($estadoRow === 'AUTORIZADO')
                                            -
                                        @else
                                            {{ $inv->pendiente_texto ?? 'N/D' }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($sale?->id && auth()->user()?->hasRole('admin'))
                                            <button type="button"
                                                class="js-consult text-xs px-3 py-1 rounded bg-blue-600 text-white hover:bg-blue-700"
                                                data-sale-id="{{ $sale->id }}">
                                                Consultar
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-400">No disponible</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-6 text-center text-blue-700" colspan="8">
                                        No hay facturas registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($invoices->hasPages())
                    <div class="p-4">
                        {{ $invoices->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div id="consult-modal" class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-black/40"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full">
                <div class="flex items-center justify-between px-4 py-3 border-b">
                    <div class="text-sm font-semibold text-blue-900">Respuesta SRI</div>
                    <button id="consult-close" class="text-blue-700 hover:text-blue-900">Cerrar</button>
                </div>
                <div class="p-4 space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-blue-700" id="consult-http">HTTP</span>
                        <span class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-800" id="consult-status">-</span>
                    </div>
                    <div>
                        <div class="text-xs text-blue-700 font-semibold mb-1">Resumen</div>
                        <div class="text-sm text-blue-900" id="consult-summary">-</div>
                    </div>
                    <div>
                        <div class="text-xs text-blue-700 font-semibold mb-1">Mensajes del SRI</div>
                        <ul class="text-xs text-blue-900 list-disc pl-5" id="consult-messages"></ul>
                    </div>
                    <details class="text-xs text-blue-700">
                        <summary class="cursor-pointer">Ver JSON completo</summary>
                        <pre id="consult-body" class="text-xs bg-blue-50 rounded p-3 overflow-x-auto overflow-y-auto max-h-64 mt-2"></pre>
                    </details>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('consult-modal');
            const closeBtn = document.getElementById('consult-close');
            const statusEl = document.getElementById('consult-status');
            const httpEl = document.getElementById('consult-http');
            const summaryEl = document.getElementById('consult-summary');
            const messagesEl = document.getElementById('consult-messages');
            const bodyEl = document.getElementById('consult-body');
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            function openModal() {
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
            }

            function clearList(el) {
                while (el.firstChild) el.removeChild(el.firstChild);
            }

            function addMsg(text) {
                const li = document.createElement('li');
                li.textContent = text;
                messagesEl.appendChild(li);
            }

            closeBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });

            document.querySelectorAll('.js-consult').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const saleId = btn.getAttribute('data-sale-id');
                    if (!saleId) return;

                    httpEl.textContent = 'HTTP';
                    statusEl.textContent = 'Consultando...';
                    summaryEl.textContent = 'Esperando respuesta...';
                    bodyEl.textContent = '';
                    clearList(messagesEl);
                    openModal();

                    try {
                        const resp = await fetch(`/sales/${saleId}/sri/consult-authorization`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({})
                        });

                        const data = await resp.json();
                        httpEl.textContent = `HTTP ${resp.status}`;
                        statusEl.textContent = data?.status || '-';

                        const auth = data?.auth || {};
                        const authEstado = auth?.estado || null;
                        const invEstado = data?.invoice?.estado_sri || null;
                        const resumen = authEstado ? `Autorizacion: ${authEstado}` : (invEstado ? `Estado factura: ${invEstado}` : 'Sin estado');
                        summaryEl.textContent = resumen;

                        const msgs = data?.invoice?.mensajes_sri_json || data?.auth?.mensajes || [];
                        if (Array.isArray(msgs) && msgs.length) {
                            msgs.forEach((m) => {
                                const base = (m?.mensaje || '').toString().trim();
                                const extra = (m?.informacionAdicional || '').toString().trim();
                                const id = (m?.identificador || '').toString().trim();
                                const line = [id ? `#${id}` : null, base, extra].filter(Boolean).join(' - ');
                                if (line) addMsg(line);
                            });
                        } else {
                            addMsg('Sin mensajes del SRI.');
                        }

                        bodyEl.textContent = JSON.stringify(data, null, 2);
                    } catch (err) {
                        httpEl.textContent = 'HTTP -';
                        statusEl.textContent = 'Error';
                        summaryEl.textContent = 'No se pudo consultar.';
                        clearList(messagesEl);
                        addMsg(err?.message || String(err));
                        bodyEl.textContent = err?.stack || String(err);
                    }
                });
            });
        })();
    </script>
</x-app-layout>
