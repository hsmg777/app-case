<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Transferencia entre bodegas') }}
            </h2>

            <button onclick="window.location.href='{{ route('inventario.index') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center">
                <x-heroicon-s-arrow-left class="w-6 h-6 mr-1" />
                Atras
            </button>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-lg shadow border border-gray-200 p-5 space-y-4">
                <h3 class="text-lg font-semibold text-blue-900">Datos de transferencia</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Bodega origen *</label>
                        <select id="bodega_origen_id" class="border rounded w-full px-3 py-2 text-sm">
                            <option value="">-- Seleccione --</option>
                            @foreach($bodegas as $bodega)
                                <option value="{{ $bodega->id }}">{{ $bodega->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Bodega destino *</label>
                        <select id="bodega_destino_id" class="border rounded w-full px-3 py-2 text-sm">
                            <option value="">-- Seleccione --</option>
                            @foreach($bodegas as $bodega)
                                <option value="{{ $bodega->id }}">{{ $bodega->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Observaciones (opcional)</label>
                        <textarea id="observaciones" rows="2" class="border rounded w-full px-3 py-2 text-sm"
                            placeholder="Ej: Reabastecimiento por rotacion de inventario"></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <div class="rounded border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                            Cada transferencia registra historico en movimientos de inventario
                            (salida en bodega origen y entrada en bodega destino).
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow border border-gray-200 p-5 space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-blue-900">Carrito de productos a transferir</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end border-b pb-4 mb-4">
                    <div class="relative md:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Producto</label>
                        <input type="text" id="product_search" class="border rounded w-full px-2 py-2 text-sm"
                            placeholder="Buscar por nombre o codigo..." oninput="buscarProductoOrigen(this.value)">
                        <input type="hidden" id="c-producto">
                        <ul id="search-results"
                            class="absolute z-10 bg-white border border-gray-300 w-full max-h-56 overflow-y-auto hidden shadow text-xs"></ul>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Cantidad</label>
                        <input id="c-cantidad" type="number" min="1" step="1"
                            class="border rounded w-full px-2 py-2 text-sm" onkeydown="handleCantidadEnter(event)">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Percha destino (opcional)</label>
                        <select id="c-percha-destino" class="border rounded w-full px-2 py-2 text-sm">
                            <option value="">Sin percha</option>
                        </select>
                    </div>

                    <div>
                        <button type="button" onclick="agregarItemTransferencia()"
                            class="w-full bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded text-sm">
                            Agregar al carrito
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                    <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs text-slate-500">Producto seleccionado</p>
                        <p id="ind-producto" class="text-sm font-semibold text-slate-800">Ninguno</p>
                    </div>
                    <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs text-slate-500">Stock actual en bodega origen</p>
                        <p id="ind-stock-total" class="text-sm font-semibold text-slate-800">0</p>
                    </div>
                    <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs text-slate-500">Stock positivo (sin negativos)</p>
                        <p id="ind-stock-positivo" class="text-sm font-semibold text-slate-800">0</p>
                    </div>
                    <div class="rounded border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-xs text-slate-500">Perchas con stock</p>
                        <p id="ind-perchas-count" class="text-sm font-semibold text-slate-800">0</p>
                    </div>
                </div>

                <div id="ind-perchas-detalle" class="mb-4 text-xs text-slate-600">
                    Selecciona una bodega origen y un producto para ver stock por percha.
                </div>

                <div class="overflow-x-auto min-h-[140px]">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-gray-700">
                                <th class="px-3 py-2 text-left">Producto</th>
                                <th class="px-3 py-2 text-left">Codigo</th>
                                <th class="px-3 py-2 text-right">Disponible origen</th>
                                <th class="px-3 py-2 text-left">Percha destino</th>
                                <th class="px-3 py-2 text-right">Cantidad a transferir</th>
                                <th class="px-3 py-2 text-center">Accion</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-items">
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-center text-gray-500">
                                    No hay productos en el carrito.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <div class="w-full md:w-1/3 border-t pt-3 text-sm">
                        <div class="flex justify-between">
                            <span>Total de productos:</span>
                            <span id="lbl-total-items" class="font-semibold">0</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Total unidades:</span>
                            <span id="lbl-total-unidades" class="font-semibold">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" onclick="guardarTransferencia()"
                    class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded shadow font-semibold">
                    Confirmar transferencia
                </button>
            </div>
        </div>
    </div>

    <script>
        const INVENTARIO_URL = '/inventario/list';
        const TRANSFER_URL = "{{ route('inventario.transferencias') }}";
        const PERCHAS_BODEGA_BASE_URL = '/inventario/perchas/bodega/';

        let DATA_INVENTARIO = [];
        let PRODUCTOS_ORIGEN = [];
        let PERCHAS_DESTINO = [];
        let CART = [];

        document.addEventListener('DOMContentLoaded', () => {
            const origen = document.getElementById('bodega_origen_id');
            const destino = document.getElementById('bodega_destino_id');

            fetch(INVENTARIO_URL, { cache: 'no-store' })
                .then(r => r.json())
                .then(data => {
                    DATA_INVENTARIO = Array.isArray(data) ? data : [];
                })
                .catch(() => {
                    DATA_INVENTARIO = [];
                });

            origen.addEventListener('change', async () => {
                syncBodegas('origen');
                await recargarInventario();
                CART = [];
                limpiarCaptura();
                renderItems();
                construirProductosOrigen();
                limpiarIndicadoresProducto();
            });

            destino.addEventListener('change', async () => {
                syncBodegas('destino');
                await recargarInventario();
                CART = [];
                limpiarCaptura();
                renderItems();
                construirProductosOrigen();
                cargarPerchasDestino();
            });
        });

        async function recargarInventario() {
            const r = await fetch(INVENTARIO_URL, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
            const data = await r.json().catch(() => []);
            DATA_INVENTARIO = Array.isArray(data) ? data : [];
        }

        function syncBodegas(changed) {
            const origen = document.getElementById('bodega_origen_id');
            const destino = document.getElementById('bodega_destino_id');
            const origenId = origen.value;
            const destinoId = destino.value;

            Array.from(origen.options).forEach(opt => {
                if (!opt.value) return;
                opt.disabled = opt.value === destinoId;
            });
            Array.from(destino.options).forEach(opt => {
                if (!opt.value) return;
                opt.disabled = opt.value === origenId;
            });

            if (origenId && destinoId && origenId === destinoId) {
                if (changed === 'origen') {
                    destino.value = '';
                } else {
                    origen.value = '';
                    CART = [];
                    limpiarCaptura();
                    renderItems();
                    construirProductosOrigen();
                    limpiarIndicadoresProducto();
                }

                PERCHAS_DESTINO = [];
                renderPerchasDestino();

                Swal.fire(
                    'Bodegas invalidas',
                    'No puedes transferir hacia la misma bodega.',
                    'warning'
                );
            }
        }

        function construirProductosOrigen() {
            const bodegaOrigenId = document.getElementById('bodega_origen_id').value;
            if (!bodegaOrigenId) {
                PRODUCTOS_ORIGEN = [];
                return;
            }

            const mapa = new Map();
            DATA_INVENTARIO
                .filter(row => String(row.bodega_id) === String(bodegaOrigenId))
                .forEach(row => {
                    const productoId = Number(row.producto_id);
                    const actual = Number(row.stock_actual || 0);
                    if (!mapa.has(productoId)) {
                        mapa.set(productoId, {
                            id: productoId,
                            nombre: row.producto?.nombre || 'N/D',
                            codigo_interno: row.producto?.codigo_interno || '',
                            codigo_barras: row.producto?.codigo_barras || '',
                            disponible_neto: 0,
                            disponible_positivo: 0,
                        });
                    }
                    const p = mapa.get(productoId);
                    p.disponible_neto += actual;
                    if (actual > 0) {
                        p.disponible_positivo += actual;
                    }
                });

            PRODUCTOS_ORIGEN = Array.from(mapa.values())
                .map(p => ({ ...p, disponible: p.disponible_positivo }))
                .filter(p => p.disponible > 0);
        }

        async function cargarPerchasDestino() {
            const bodegaDestinoId = document.getElementById('bodega_destino_id').value;
            if (!bodegaDestinoId) {
                PERCHAS_DESTINO = [];
                renderPerchasDestino();
                return;
            }

            try {
                const r = await fetch(`${PERCHAS_BODEGA_BASE_URL}${bodegaDestinoId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await r.json().catch(() => []);
                PERCHAS_DESTINO = Array.isArray(data) ? data : [];
            } catch {
                PERCHAS_DESTINO = [];
            }

            renderPerchasDestino();
        }

        function renderPerchasDestino() {
            const select = document.getElementById('c-percha-destino');
            if (!select) return;

            select.innerHTML = '<option value="">Sin percha</option>';
            PERCHAS_DESTINO.forEach(percha => {
                const opt = document.createElement('option');
                opt.value = percha.id;
                opt.textContent = percha.codigo || `Percha ${percha.id}`;
                select.appendChild(opt);
            });
        }

        function buscarProductoOrigen(texto) {
            const list = document.getElementById('search-results');
            const hidden = document.getElementById('c-producto');

            if (!texto.trim()) {
                list.innerHTML = '';
                list.classList.add('hidden');
                hidden.value = '';
                return;
            }

            const bodegaOrigenId = document.getElementById('bodega_origen_id').value;
            if (!bodegaOrigenId) {
                list.innerHTML = '<li class="px-3 py-2 text-gray-500">Seleccione una bodega origen</li>';
                list.classList.remove('hidden');
                return;
            }

            const term = texto.trim().toLowerCase();
            const results = PRODUCTOS_ORIGEN.filter(p => {
                return (p.nombre || '').toLowerCase().includes(term)
                    || (p.codigo_interno || '').toLowerCase().includes(term)
                    || (p.codigo_barras || '').toLowerCase().includes(term);
            }).slice(0, 10);

            list.innerHTML = '';
            if (results.length === 0) {
                list.innerHTML = '<li class="px-3 py-2 text-gray-500">No encontrado</li>';
            } else {
                results.forEach(prod => {
                    const li = document.createElement('li');
                    li.className = 'px-3 py-2 hover:bg-blue-100 cursor-pointer border-b last:border-0';
                    li.textContent = `${prod.nombre} | ${prod.codigo_interno || '--'} | Stock: ${prod.disponible}`;
                    li.onclick = () => seleccionarProducto(prod);
                    list.appendChild(li);
                });
            }
            list.classList.remove('hidden');
        }

        function seleccionarProducto(prod) {
            document.getElementById('product_search').value = prod.nombre;
            document.getElementById('c-producto').value = prod.id;
            document.getElementById('search-results').classList.add('hidden');
            actualizarIndicadoresProducto(prod.id);
        }

        function handleCantidadEnter(event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            agregarItemTransferencia();
        }

        function agregarItemTransferencia() {
            const productoId = Number(document.getElementById('c-producto').value);
            const cantidad = Number(document.getElementById('c-cantidad').value);
            const perchaDestinoIdRaw = document.getElementById('c-percha-destino').value;
            const perchaDestinoId = perchaDestinoIdRaw ? Number(perchaDestinoIdRaw) : null;
            const producto = PRODUCTOS_ORIGEN.find(p => p.id === productoId);
            const perchaDestino = PERCHAS_DESTINO.find(p => Number(p.id) === Number(perchaDestinoId));

            if (!productoId || !cantidad || cantidad < 1 || !producto) {
                Swal.fire('Datos incompletos', 'Selecciona un producto y una cantidad valida.', 'warning');
                return;
            }

            const existente = CART.find(i =>
                i.producto_id === productoId &&
                Number(i.percha_destino_id || 0) === Number(perchaDestinoId || 0)
            );
            const cantidadActual = existente ? Number(existente.cantidad) : 0;
            if (cantidad + cantidadActual > producto.disponible) {
                Swal.fire('Stock insuficiente', `Disponible en origen: ${producto.disponible}`, 'warning');
                return;
            }

            if (existente) {
                existente.cantidad += cantidad;
            } else {
                CART.push({
                    producto_id: producto.id,
                    producto_nombre: producto.nombre,
                    codigo_interno: producto.codigo_interno || '-',
                    disponible: producto.disponible,
                    percha_destino_id: perchaDestinoId,
                    percha_destino_codigo: perchaDestino ? (perchaDestino.codigo || '-') : 'Sin percha',
                    cantidad: cantidad,
                });
            }

            limpiarCaptura();
            renderItems();
            actualizarIndicadoresProducto(productoId);
        }

        function limpiarCaptura() {
            document.getElementById('product_search').value = '';
            document.getElementById('c-producto').value = '';
            document.getElementById('c-cantidad').value = '';
            document.getElementById('c-percha-destino').value = '';
            document.getElementById('search-results').classList.add('hidden');
        }

        function limpiarIndicadoresProducto() {
            document.getElementById('ind-producto').textContent = 'Ninguno';
            document.getElementById('ind-stock-total').textContent = '0';
            document.getElementById('ind-stock-positivo').textContent = '0';
            document.getElementById('ind-perchas-count').textContent = '0';
            document.getElementById('ind-perchas-detalle').textContent =
                'Selecciona una bodega origen y un producto para ver stock por percha.';
        }

        function actualizarIndicadoresProducto(productoId) {
            const bodegaOrigenId = document.getElementById('bodega_origen_id').value;
            if (!bodegaOrigenId || !productoId) {
                limpiarIndicadoresProducto();
                return;
            }

            const rows = DATA_INVENTARIO.filter(row =>
                String(row.bodega_id) === String(bodegaOrigenId) &&
                String(row.producto_id) === String(productoId)
            );

            if (!rows.length) {
                limpiarIndicadoresProducto();
                return;
            }

            const productoNombre = rows[0]?.producto?.nombre || 'N/D';
            const total = rows.reduce((acc, row) => acc + Number(row.stock_actual || 0), 0);
            const stockPositivo = rows
                .filter(r => Number(r.stock_actual || 0) > 0)
                .reduce((acc, row) => acc + Number(row.stock_actual || 0), 0);
            const perchasConStock = rows.filter(r => Number(r.stock_actual || 0) > 0).length;
            const detalle = rows
                .sort((a, b) => String(a.percha?.codigo || '').localeCompare(String(b.percha?.codigo || '')))
                .map(r => `${r.percha?.codigo || 'Sin percha'}: ${Number(r.stock_actual || 0)}`)
                .join(' | ');

            document.getElementById('ind-producto').textContent = productoNombre;
            document.getElementById('ind-stock-total').textContent = String(total);
            document.getElementById('ind-stock-positivo').textContent = String(stockPositivo);
            document.getElementById('ind-perchas-count').textContent = String(perchasConStock);
            document.getElementById('ind-perchas-detalle').textContent = detalle || 'Sin registros de percha.';
        }

        function renderItems() {
            const tbody = document.getElementById('tbody-items');

            if (!CART.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-gray-500">
                            No hay productos en el carrito.
                        </td>
                    </tr>
                `;
                actualizarResumen();
                return;
            }

            tbody.innerHTML = CART.map((item, idx) => `
                <tr class="border-b last:border-0">
                    <td class="px-3 py-2">${escapeHtml(item.producto_nombre)}</td>
                    <td class="px-3 py-2">${escapeHtml(item.codigo_interno)}</td>
                    <td class="px-3 py-2 text-right">${item.disponible}</td>
                    <td class="px-3 py-2">${escapeHtml(item.percha_destino_codigo || 'Sin percha')}</td>
                    <td class="px-3 py-2 text-right">
                        <input type="number" min="1" step="1" value="${item.cantidad}"
                            onchange="updateCantidad(${idx}, this.value)"
                            class="border rounded px-2 py-1 w-24 text-right text-sm">
                    </td>
                    <td class="px-3 py-2 text-center">
                        <button type="button" class="text-red-600 hover:text-red-800"
                            onclick="removeItem(${idx})">Quitar</button>
                    </td>
                </tr>
            `).join('');

            actualizarResumen();
        }

        function updateCantidad(index, value) {
            const nuevaCantidad = Number(value || 0);
            if (nuevaCantidad < 1) {
                Swal.fire('Cantidad invalida', 'La cantidad debe ser mayor a 0.', 'warning');
                renderItems();
                return;
            }

            const item = CART[index];
            if (nuevaCantidad > item.disponible) {
                Swal.fire('Stock insuficiente', `Disponible en origen: ${item.disponible}`, 'warning');
                renderItems();
                return;
            }

            item.cantidad = nuevaCantidad;
            actualizarResumen();
            actualizarIndicadoresProducto(item.producto_id);
        }

        function removeItem(index) {
            CART.splice(index, 1);
            renderItems();
        }

        function actualizarResumen() {
            const totalItems = CART.length;
            const totalUnidades = CART.reduce((acc, it) => acc + Number(it.cantidad || 0), 0);
            document.getElementById('lbl-total-items').textContent = totalItems;
            document.getElementById('lbl-total-unidades').textContent = totalUnidades;
        }

        async function guardarTransferencia() {
            const bodegaOrigenId = document.getElementById('bodega_origen_id').value;
            const bodegaDestinoId = document.getElementById('bodega_destino_id').value;
            const observaciones = document.getElementById('observaciones').value.trim();

            if (!bodegaOrigenId || !bodegaDestinoId) {
                Swal.fire('Datos incompletos', 'Selecciona bodega origen y destino.', 'warning');
                return;
            }

            if (String(bodegaOrigenId) === String(bodegaDestinoId)) {
                Swal.fire('Bodegas invalidas', 'La bodega origen y destino deben ser diferentes.', 'warning');
                return;
            }

            if (!CART.length) {
                Swal.fire('Sin productos', 'Agrega al menos un producto al carrito.', 'warning');
                return;
            }

            // Revalidar contra inventario actual antes de enviar (evita desfaces visuales)
            await recargarInventario();
            construirProductosOrigen();

            const invalido = CART.find(it => {
                const actual = PRODUCTOS_ORIGEN.find(p => Number(p.id) === Number(it.producto_id));
                const disponibleActual = Number(actual?.disponible || 0);
                return Number(it.cantidad) > disponibleActual;
            });

            if (invalido) {
                const actual = PRODUCTOS_ORIGEN.find(p => Number(p.id) === Number(invalido.producto_id));
                const disponibleActual = Number(actual?.disponible || 0);
                actualizarIndicadoresProducto(invalido.producto_id);
                Swal.fire(
                    'Stock actualizado',
                    `El stock actual para ${invalido.producto_nombre} es ${disponibleActual}. Ajusta la cantidad e intenta de nuevo.`,
                    'warning'
                );
                return;
            }

            const payload = {
                bodega_origen_id: Number(bodegaOrigenId),
                bodega_destino_id: Number(bodegaDestinoId),
                observaciones: observaciones || null,
                items: CART.map(i => ({
                    producto_id: i.producto_id,
                    cantidad: Number(i.cantidad),
                    percha_destino_id: i.percha_destino_id || null,
                })),
            };

            try {
                Swal.fire({
                    title: 'Procesando transferencia...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const resp = await fetch(TRANSFER_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(payload)
                });

                const data = await resp.json().catch(() => ({}));
                if (!resp.ok) {
                    throw data;
                }

                await Swal.fire('Transferencia registrada', data.message || 'Proceso completado.', 'success');
                window.location.reload();
            } catch (err) {
                Swal.fire('Error', err.message || 'No se pudo registrar la transferencia.', 'error');
            }
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</x-app-layout>
