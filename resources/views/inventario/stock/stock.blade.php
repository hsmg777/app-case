<x-app-layout>

    {{-- HEADER --}}
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Inventario - Stock') }}
            </h2>

            <button 
                onclick="history.back()"
                class="text-blue-700 hover:text-blue-900 transition flex items-center"
            >
                <x-heroicon-s-arrow-left class="w-6 h-6 mr-1" />
                Atrás
            </button>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">

            {{-- ============================
                    LAYOUT PRINCIPAL
            ============================= --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- ============================
                        LISTA SCROLLEABLE
                ============================= --}}
                <div class="col-span-1 bg-white rounded-lg shadow border border-gray-200 p-4">

                    <h3 class="text-xl font-semibold text-blue-900 mb-3">
                        Productos en Inventario
                    </h3>

                    <input 
                        type="text" 
                        id="buscar"
                        placeholder="Buscar producto..."
                        class="border rounded w-full px-3 py-2 mb-3"
                        oninput="filtrarLista()"
                    >

                    <div id="lista-stock"
                         class="space-y-2 overflow-y-auto"
                         style="max-height: calc(100vh - 230px)">
                    </div>

                </div>

                {{-- ============================
                        PANEL DETALLE
                ============================= --}}
                <div class="col-span-2 bg-white rounded-lg shadow border border-gray-200 p-6">

                    <div id="panel-placeholder" class="text-gray-400 text-center py-20">
                        Selecciona un producto para ver el detalle
                    </div>

                    <div id="panel-detalle" class="hidden">

                        <h3 class="text-2xl font-bold text-blue-900" id="p-nombre"></h3>

                        <div class="mt-4 grid grid-cols-2 gap-4">

                            <p class="text-gray-700">
                                <strong>Bodega:</strong> 
                                <span id="p-bodega"></span>
                            </p>

                            <p class="text-gray-700">
                                <strong>Percha:</strong> 
                                <span id="p-percha"></span>
                            </p>

                            <p class="text-gray-700 col-span-2 text-xl">
                                <strong>Stock actual:</strong> 
                                <span id="p-stock"></span>
                            </p>

                            <p class="text-gray-700 col-span-2">
                                <strong>Estado:</strong> 
                                <span id="p-estado" class="font-bold"></span>
                            </p>

                        </div>

                        <div class="mt-6 flex space-x-4">

                            <button 
                                onclick="openIncrease()"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded flex items-center">
                                <x-heroicon-s-plus class="w-5 h-5 mr-1" /> Aumentar
                            </button>

                            <button 
                                onclick="openDecrease()"
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded flex items-center">
                                <x-heroicon-s-minus class="w-5 h-5 mr-1" /> Disminuir
                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </div>
    </div>

    {{-- ============================
            MODALES
    ============================= --}}
    @include('inventario.stock.modals.increase')
    @include('inventario.stock.modals.decrease')


{{-- ===========================================================
                        JAVASCRIPT
=========================================================== --}}
<script>
let DATA = [];
let seleccion = null;

document.addEventListener("DOMContentLoaded", () => {
    cargarStock();
});

/* =======================================================
                CARGAR LISTA
======================================================= */
function cargarStock() {
    fetch("/inventario/list")
        .then(r => r.json())
        .then(data => {
            DATA = data;
            renderLista(data);
        });
}

function renderLista(lista) {
    const cont = document.getElementById("lista-stock");
    cont.innerHTML = "";

    lista.forEach(item => {
        const minimo = item.producto.stock_minimo ?? 0;
        const esBajo = item.stock_actual < minimo;

        const html = `
            <div onclick="verDetalle(${item.id})"
                class="p-3 border rounded cursor-pointer transition
                       ${esBajo ? 'bg-red-50 border-red-300' : 'hover:bg-blue-50'}">

                <p class="font-semibold ${esBajo ? 'text-red-700' : 'text-blue-900'}">
                    ${item.producto.nombre}
                </p>

                <p class="text-sm text-gray-600">${item.bodega.nombre}</p>

                <p class="text-sm mt-1">
                    Stock:
                    <span class="font-bold ${esBajo ? 'text-red-700' : 'text-green-700'}">
                        ${item.stock_actual}
                    </span>
                </p>

                ${esBajo ? `<p class="text-xs text-red-600 mt-1">Stock bajo</p>` : ''}
            </div>
        `;
        cont.innerHTML += html;
    });
}

/* =======================================================
                FILTRAR
======================================================= */
function filtrarLista() {
    const t = document.getElementById("buscar").value.toLowerCase();
    const f = DATA.filter(x =>
        x.producto.nombre.toLowerCase().includes(t)
    );
    renderLista(f);
}

/* =======================================================
                DETALLE
======================================================= */
function verDetalle(id) {
    seleccion = DATA.find(x => x.id === id);

    if (!seleccion) return;

    document.getElementById("panel-placeholder").classList.add("hidden");
    document.getElementById("panel-detalle").classList.remove("hidden");

    document.getElementById("p-nombre").innerText = seleccion.producto.nombre;
    document.getElementById("p-bodega").innerText = seleccion.bodega.nombre;
    document.getElementById("p-percha").innerText = seleccion.percha?.codigo ?? "—";
    document.getElementById("p-stock").innerText = seleccion.stock_actual;

    const minimo = seleccion.producto.stock_minimo ?? 0;
    const e = document.getElementById("p-estado");

    if (seleccion.stock_actual < minimo) {
        e.innerText = "Bajo";
        e.className = "font-bold text-red-700";
    } else {
        e.innerText = "Normal";
        e.className = "font-bold text-green-700";
    }
}

/* =======================================================
                MODALES (OPEN)
======================================================= */
function openIncrease() {
    document.getElementById("increase-cantidad").value = "";
    document.getElementById("modal-increase").classList.remove("hidden");
}

function openDecrease() {
    document.getElementById("decrease-cantidad").value = "";
    document.getElementById("current-stock").innerText = seleccion.stock_actual;
    document.getElementById("modal-decrease").classList.remove("hidden");
}

/* =======================================================
                MODALES (CLOSE)
======================================================= */
function closeIncrease() {
    document.getElementById("modal-increase").classList.add("hidden");
}

function closeDecrease() {
    document.getElementById("modal-decrease").classList.add("hidden");
}

/* =======================================================
                SUBMIT INCREASE
======================================================= */
function submitIncrease(e) {
    e.preventDefault();

    fetch(`/inventario/increase`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            producto_id: seleccion.producto_id,
            bodega_id: seleccion.bodega_id,
            percha_id: seleccion.percha_id,
            cantidad: document.getElementById("increase-cantidad").value
        })
    })
    .then(r => r.json())
    .then(() => {
        closeIncrease();
        cargarStock();
        setTimeout(() => verDetalle(seleccion.id), 200);
    });
}

/* =======================================================
                SUBMIT DECREASE
======================================================= */
function submitDecrease(e) {
    e.preventDefault();

    fetch(`/inventario/decrease`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            producto_id: seleccion.producto_id,
            bodega_id: seleccion.bodega_id,
            percha_id: seleccion.percha_id,
            cantidad: document.getElementById("decrease-cantidad").value
        })
    })
    .then(r => r.json())
    .then(() => {
        closeDecrease();
        cargarStock();
        setTimeout(() => verDetalle(seleccion.id), 200);
    });
}

</script>

</x-app-layout>
