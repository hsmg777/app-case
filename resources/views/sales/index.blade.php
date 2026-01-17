<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-slate-900 leading-tight">
                {{ __('Ventas / Facturación') }}
            </h2>

            <button
                onclick="window.location.href='{{ route('dashboard') }}'"
                class="text-slate-600 hover:text-slate-900 transition flex items-center space-x-1 text-sm"
                title="Regresar"
            >
                <x-heroicon-s-arrow-left class="w-5 h-5" />
                <span>Atrás</span>
            </button>
        </div>
    </x-slot>
    @php
        $cajaId = session('caja_id') ?? request('caja_id');
        $returnTo = url()->full();
    @endphp


    <div class="py-4">
        <div class="max-w-7xl mx-auto px-3 lg:px-4">

            {{-- LAYOUT POS: DOS COLUMNAS --}}
            {{-- Aumentamos min-h para dar mas espacio vertical --}}
            <div class="flex flex-col lg:flex-row gap-3 h-[calc(100vh-4rem)] min-h-[850px]">

                {{-- =============== COL IZQUIERDA: PRODUCTOS (HACER MAS ANGOSTA) =============== --}}
                <section class="flex-[0.9] flex flex-col gap-4 min-h-0">

                    {{-- HEADERS OCULTOS (Inputs necesarios para JS) --}}
                    <div class="hidden">
                         <input type="hidden" id="caja_id" value="{{ $cajaId ?? '' }}">
                         {{-- JS busca 'tipo_documento' --}}
                         <select id="tipo_documento"><option value="FACTURA">Factura electrónica</option></select>
                         {{-- JS busca 'fecha_venta' --}}
                         <input type="hidden" id="fecha_venta" value="{{ now()->format('Y-m-d\TH:i') }}">
                         {{-- JS busca 'bodega_id' --}}
                         <input type="hidden" id="bodega_id" value="{{ $bodegaSelected->id }}">
                    </div>

                    {{-- Tarjeta de productos grande --}}
                    <div class="bg-white border border-slate-200 rounded-3xl shadow-lg flex flex-col overflow-hidden flex-1 min-h-0">
                        {{-- HEADER productos --}}
                        <header class="px-3 py-3 border-b border-slate-100 bg-slate-50/50">
                            {{-- Título solicitado --}}
                            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider text-center mb-2">
                                Productos
                            </h3>
                            
                            <div class="flex items-center gap-2">
                                <div class="flex-1 relative">
                                    <input
                                        type="text"
                                        id="item_descripcion"
                                        class="w-full border-slate-200 rounded-full pl-8 pr-3 py-2 text-sm shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-slate-50"
                                        placeholder="Buscar producto..."
                                        autocomplete="off"
                                    >
                                    <span class="absolute left-2.5 top-[9px] text-slate-400 text-sm">
                                        <x-heroicon-s-magnifying-glass class="w-4 h-4" />
                                    </span>

                                    {{-- Sugerencias --}}
                                    <div
                                        id="product_suggestions"
                                        class="hidden border border-slate-200 bg-white rounded-xl shadow-xl text-xs max-h-56 overflow-y-auto mt-1 z-20"
                                    ></div>
                                </div>
                            </div>

                            {{-- Inputs ocultos para JS actual --}}
                            <div class="hidden">
                                <input type="number" id="item_cantidad" value="1" min="1">
                                <input type="number" id="item_precio_unitario" step="0.01" min="0">
                                <input type="number" id="item_descuento" step="0.01" min="0" value="0">
                                <button type="button" id="btn-add-item"></button>
                            </div>
                        </header>

                        {{-- LISTA SCROLLABLE --}}
                        <div class="flex-1 px-2 pb-2 pt-2 min-h-0">
                            <div
                                class="w-full h-full border border-slate-100 rounded-xl bg-slate-50/50 overflow-y-auto"
                            >
                                <div
                                    id="product_list"
                                    data-product-url="{{ url('/productos/list') }}"
                                    class="divide-y divide-slate-100 text-sm"
                                >
                                    {{-- Render de productos --}}
                                </div>

                                <div id="product_list_empty" class="py-6 flex items-center justify-center">
                                    <p class="text-[13px] text-slate-400 text-center px-6">
                                        LISTA DE LOS PRODUCTOS. Escribe en el buscador para comenzar a buscar y agregar al carrito.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- =============== COL DERECHA: CLIENTE + CARRITO (HACER MAS ANCHA) =============== --}}
                <section class="flex-[1.4] flex flex-col gap-4">
                                        {{-- CLIENTE (Movido aquí) --}}
                    <div class="bg-white border border-slate-200 rounded-3xl shadow-lg overflow-hidden shrink-0">
                        <div class="flex items-center justify-between px-3 py-2 bg-slate-100">
                            <div class="min-w-0">
                                <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">
                                    Cliente
                                </p>
                                <p id="cliente_nombre"
                                   class="text-xs font-bold text-slate-800 truncate">
                                    Consumidor final
                                </p>
                                <input
                                type="hidden"
                                id="client_id"
                                value=""
                                data-cf-name="CONSUMIDOR FINAL"
                                data-cf-ident="9999999999999"
                                />
                                <p id="cliente_identificacion" class="text-[10px] text-slate-400 truncate">
                                    Cédula o RUC aquí
                                </p>
                            </div>

                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    id="btn-open-client-modal"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-600 text-white text-sm font-bold shadow hover:bg-blue-700"
                                    title="Agregar / seleccionar cliente"
                                >
                                    +
                                </button>
                                <button
                                    type="button"
                                    id="btn-search-client"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-900 text-white shadow hover:bg-black"
                                    title="Buscar cliente"
                                >
                                    <x-heroicon-s-magnifying-glass class="w-3 h-3" />
                                </button>
                            </div>
                        </div>

                        <div class="px-3 py-2 bg-white flex flex-col gap-1">
                            <select
                                id="cliente_email"
                                class="w-full border-slate-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-[11px] h-7 py-0 pl-2"
                            >
                                <option value="">Selecciona un correo (opcional)</option>
                            </select>
                            <p id="cliente_email_resumen" class="hidden text-[10px] text-slate-400">
                                Sin correo seleccionado
                            </p>
                        </div>
                    </div>
                    <div class="flex-1 bg-white border border-slate-200 rounded-3xl shadow-lg flex flex-col overflow-hidden">

                        {{-- CABECERA --}}
                        <header class="px-5 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <p class="text-[11px] text-slate-500 uppercase font-semibold">
                                    Carrito
                                </p>
                                <p class="text-[11px] text-slate-400">
                                    Detalle de la venta actual
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] text-slate-500 uppercase font-semibold">Total</p>
                                <p id="resumen-total" class="text-2xl font-bold text-blue-700">$ 0.00</p>
                            </div>
                        </header>

                        {{-- LISTA DE ÍTEMS (MUCHO MÁS ALTA) --}}
                        <div class="flex-1 overflow-y-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-[13px]">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase text-[10px]">Producto</th>
                                        <th class="px-2 py-2 text-right font-semibold text-slate-500 uppercase text-[10px]">Cantidad</th>
                                        <th class="px-2 py-2 text-right font-semibold text-slate-500 uppercase text-[10px]">P. unitar</th>
                                        <th class="px-2 py-2 text-right font-semibold text-slate-500 uppercase text-[10px]">Descuento</th>
                                        <th class="px-2 py-2 text-right font-semibold text-slate-500 uppercase text-[10px]">Total</th>
                                        <th class="px-2 py-2 text-center font-semibold text-slate-500 uppercase text-[10px]">Acc.</th>
                                    </tr>
                                </thead>
                                <tbody id="cart-body" class="divide-y divide-slate-100 bg-white">
                                    {{-- Filas dinámicas --}}
                                </tbody>
                            </table>

                            <div id="empty-cart-row" class="px-4 py-6 text-center text-[12px] text-slate-400">
                                Lista de los productos añadidos aparecerá aquí.
                            </div>
                        </div>

                        {{-- TOTALES + OBSERVACIONES + COBRAR --}}
                        <footer class="border-t border-slate-100 bg-white px-5 pt-3 pb-4 space-y-3">
                            <div class="space-y-1.5 text-[12px] text-slate-600">
                                <div class="flex justify-between">
                                    <span>Subtotal</span>
                                    <span id="resumen-subtotal">$ 0.00</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Descuento</span>
                                    <span id="resumen-descuento">$ 0.00</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Impuesto</span>
                                    <span id="resumen-impuesto">$ 0.00</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <label class="inline-flex items-center gap-2 text-[12px] text-slate-700 select-none">
                                        <input
                                        id="toggle_iva_global"
                                        type="checkbox"
                                        checked
                                        class="rounded border-slate-300"
                                        />
                                        <span id="iva_label_text">Aplicar IVA (15%)</span>
                                    </label>

                                    <span id="resumen-iva">$ 0.00</span>

                                    <!-- ✅ hidden para mandar al backend -->
                                    <input type="hidden" id="iva_enabled" value="1">
                                </div>




                            </div>

                            <div class="space-y-3">
                                {{-- Método de pago --}}
                                <div class="flex flex-col space-y-1">
                                    <label class="text-[11px] text-slate-500 uppercase font-semibold">
                                        Método de pago
                                    </label>
                                    <select
                                        id="payment_modal_metodo"
                                        class="border-slate-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-xs h-9"
                                    >
                                        @foreach($paymentMethods as $pm)
                                            <option value="{{ $pm->nombre }}" data-id="{{ $pm->id }}" {{ $pm->nombre == 'EFECTIVO' ? 'selected' : '' }}>
                                                {{ $pm->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Monto recibido --}}
                                <div class="flex flex-col space-y-1">
                                    <label class="text-[11px] text-slate-500 uppercase font-semibold">
                                        Monto recibido ($)
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        id="payment_modal_monto_recibido"
                                        class="border-slate-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm h-9"
                                        placeholder="0.00"
                                    >
                                </div>

                                {{-- Referencia y Observaciones (Pago) --}}
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="flex flex-col space-y-1">
                                        <label class="text-[10px] text-slate-400 uppercase font-semibold">
                                            Referencia
                                        </label>
                                        <input
                                            type="text"
                                            id="payment_modal_referencia"
                                            class="border-slate-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-xs h-8"
                                            placeholder="Nro. voucher..."
                                        >
                                    </div>
                                    <div class="flex flex-col space-y-1">
                                        <label class="text-[10px] text-slate-400 uppercase font-semibold">
                                            Observaciones
                                        </label>
                                        <textarea
                                            id="payment_modal_observaciones"
                                            rows="1"
                                            class="border-slate-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-xs py-1"
                                            placeholder="Notas..."
                                        ></textarea>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                id="btn-confirm-payment"
                                class="w-full inline-flex justify-center items-center gap-2 px-4 py-3 bg-emerald-600 border border-transparent rounded-2xl font-semibold text-sm text-white uppercase tracking-wide shadow-xl hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                            >
                                <x-heroicon-s-currency-dollar class="w-5 h-5" />
                                <span>COBRAR</span>
                            </button>
                            @php
                                $hasCaja = !empty($cajaId);
                            @endphp

                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    id="btn-open-cash-in"
                                    class="w-full inline-flex justify-center items-center px-2 py-2 rounded-2xl font-semibold text-xs uppercase tracking-wide shadow
                                        {{ $hasCaja ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-slate-200 text-slate-400 cursor-not-allowed' }}"
                                    {{ $hasCaja ? '' : 'disabled' }}
                                    title="{{ $hasCaja ? 'Registrar ingreso de caja' : 'Primero abre caja' }}"
                                >
                                    Ingreso
                                </button>

                                <button
                                    type="button"
                                    id="btn-open-cash-out"
                                    class="w-full inline-flex justify-center items-center px-2 py-2 rounded-2xl font-semibold text-xs uppercase tracking-wide shadow
                                        {{ $hasCaja ? 'bg-amber-600 text-white hover:bg-amber-700' : 'bg-slate-200 text-slate-400 cursor-not-allowed' }}"
                                    {{ $hasCaja ? '' : 'disabled' }}
                                    title="{{ $hasCaja ? 'Registrar retiro de caja' : 'Primero abre caja' }}"
                                >
                                    Retiro
                                </button>

                                {{-- Cerrar Caja (Movido aquí) --}}
                                @if($cajaId)
                                    <a
                                        href="{{ route('cashier.close.view', ['caja_id' => $cajaId]) }}"
                                        class="w-full inline-flex justify-center items-center px-2 py-2 rounded-2xl bg-rose-600 text-white text-xs font-semibold uppercase tracking-wide shadow hover:bg-rose-700 transition lg:truncate"
                                        title="Cerrar caja"
                                    >
                                        Cerrar Caja
                                    </a>
                                @else
                                    <button
                                        type="button"
                                        class="w-full inline-flex justify-center items-center px-2 py-2 rounded-2xl bg-slate-200 text-slate-400 text-xs font-semibold uppercase tracking-wide shadow cursor-not-allowed lg:truncate"
                                        title="Primero abre caja"
                                        disabled
                                    >
                                        Cerrar Caja
                                    </button>
                                @endif
                            </div>
                        </footer>
                    </div>
                </section>
            </div>

            {{-- MODALES --}}
            {{-- REMOVED: @include('sales.partials.payment-modal') --}}
            @include('sales.partials.change-modal')
            @include('sales.partials.client-modal')
            @include('clients.modals.create')
            @include('sales.partials.cash-in-modal')
            @include('sales.partials.cash-out-modal')



        </div>
        <iframe
        id="ticketPrintFrame"
        style="position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;"
        ></iframe>

        <script>
        window.addEventListener('message', (e) => {
            if (e?.data?.type === 'ticket-printed') {
            const f = document.getElementById('ticketPrintFrame');
            if (f) f.src = 'about:blank';
            }
        });
        </script>

    </div>

    <script>
        window.SALES_ROUTES = {
            store: "{{ route('api.ventas.store') }}",
            productSearch: "{{ url('/productos/list') }}",
            clientIndex: "{{ route('clients.index') }}",
            clientEmailsBase: "{{ url('/clients') }}",
            cashierOpen: "{{ route('cashier.open.view') }}",
            cashierMovement: "{{ route('cashier.movement') }}",

        };
        window.CSRF_TOKEN = "{{ csrf_token() }}";

        window.AUTH_USER_ID = @json(auth()->id());

        console.log('[POS] AUTH_USER_ID =', window.AUTH_USER_ID);
    </script>

    <script>
    (function () {
        const t = document.getElementById('toggle_iva_global');
        const label = document.getElementById('iva_label_text');
        const hidden = document.getElementById('iva_enabled');

        function sync() {
        const on = !!t?.checked;
        if (label) label.textContent = on ? 'Aplicar IVA (15%)' : 'IVA desactivado (0%)';
        if (hidden) hidden.value = on ? '1' : '0';
        }

        if (t) t.addEventListener('change', sync);
        sync();
    })();
    </script>
    <script>
        (function () {
        const cajaInput = document.getElementById('caja_id');

        const inModal = document.getElementById('cashInModal');
        const outModal = document.getElementById('cashOutModal');

        const btnOpenIn = document.getElementById('btn-open-cash-in');
        const btnOpenOut = document.getElementById('btn-open-cash-out');

        const btnSubmitIn = document.getElementById('btnSubmitCashIn');
        const btnSubmitOut = document.getElementById('btnSubmitCashOut');

        function openModal(el) { if (el) el.classList.remove('hidden'); }
        function closeModal(el) { if (el) el.classList.add('hidden'); }

        document.addEventListener('click', (e) => {
            const target = e.target;
            const id = target?.getAttribute?.('data-close');
            if (!id) return;
            const m = document.getElementById(id);
            closeModal(m);
        });

        function getCajaId() {
            const v = Number(cajaInput?.value || 0);
            return Number.isFinite(v) ? v : 0;
        }

        async function sendMovement(type, amount, reason) {
            const cajaId = getCajaId();
            if (!cajaId) {
            window.Swal?.fire('Caja no abierta', 'Primero debes abrir una caja.', 'warning');
            return;
            }

            const url = window.SALES_ROUTES?.cashierMovement;
            if (!url) {
            window.Swal?.fire('Error', 'No se encontró la ruta cashierMovement.', 'error');
            return;
            }

            const fd = new FormData();
            fd.append('caja_id', String(cajaId));
            fd.append('type', type); // IN / OUT
            fd.append('amount', String(amount));
            fd.append('reason', reason);

            const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: fd
            });

            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
            const msg =
                data?.message ||
                (data?.errors ? Object.values(data.errors).flat().join('\n') : 'No se pudo registrar el movimiento.');
            throw new Error(msg);
            }

            return data;
        }

        btnOpenIn?.addEventListener('click', () => openModal(inModal));
        btnOpenOut?.addEventListener('click', () => openModal(outModal));

        btnSubmitIn?.addEventListener('click', async () => {
            const amount = Number(document.getElementById('cashInAmount')?.value || 0);
            const reason = (document.getElementById('cashInReason')?.value || '').trim();

            if (!(amount > 0)) return window.Swal?.fire('Falta monto', 'Ingresa un monto válido.', 'warning');
            if (!reason) return window.Swal?.fire('Falta motivo', 'Ingresa el motivo del ingreso.', 'warning');

            try {
            const r = await sendMovement('IN', amount, reason);
            closeModal(inModal);
            document.getElementById('cashInAmount').value = '';
            document.getElementById('cashInReason').value = '';
            window.Swal?.fire('Guardado', r?.message || 'Ingreso registrado.', 'success');
            } catch (err) {
            window.Swal?.fire('Error', err.message || 'No se pudo registrar el ingreso.', 'error');
            }
        });

        btnSubmitOut?.addEventListener('click', async () => {
            const amount = Number(document.getElementById('cashOutAmount')?.value || 0);
            const reason = (document.getElementById('cashOutReason')?.value || '').trim();

            if (!(amount > 0)) return window.Swal?.fire('Falta monto', 'Ingresa un monto válido.', 'warning');
            if (!reason) return window.Swal?.fire('Falta motivo', 'Ingresa el motivo del retiro.', 'warning');

            try {
            const r = await sendMovement('OUT', amount, reason);
            closeModal(outModal);
            document.getElementById('cashOutAmount').value = '';
            document.getElementById('cashOutReason').value = '';
            window.Swal?.fire('Guardado', r?.message || 'Retiro registrado.', 'success');
            } catch (err) {
            window.Swal?.fire('Error', err.message || 'No se pudo registrar el retiro.', 'error');
            }
        });

        })();
        </script>



</x-app-layout>
