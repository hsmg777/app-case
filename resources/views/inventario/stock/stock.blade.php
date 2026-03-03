<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Inventario - Stock') }}
            </h2>

            <button
                onclick="window.location.href='{{ route('inventario.index') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center"
            >
                <x-heroicon-s-arrow-left class="w-6 h-6 mr-1" />
                Atras
            </button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-6 lg:px-8 space-y-5">
            <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label for="buscar" class="block text-xs font-semibold text-slate-600 mb-1">Buscar</label>
                        <input
                            type="text"
                            id="buscar"
                            placeholder="Producto, codigo interno, codigo barras..."
                            class="border rounded w-full px-3 py-2"
                        >
                    </div>

                    <div>
                        <label for="filter-bodega" class="block text-xs font-semibold text-slate-600 mb-1">Bodega</label>
                        <select id="filter-bodega" class="border rounded w-full px-3 py-2">
                            <option value="">Todas las bodegas</option>
                        </select>
                    </div>

                    <div>
                        <label for="filter-categoria" class="block text-xs font-semibold text-slate-600 mb-1">Categoria</label>
                        <select id="filter-categoria" class="border rounded w-full px-3 py-2">
                            <option value="">Todas las categorias</option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input id="filter-low" type="checkbox" class="rounded border-slate-300">
                            Mostrar solo stock bajo
                        </label>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Producto</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Codigo interno</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Bodega</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Percha</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Stock</th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-700">Stock min</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Estado</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="stock-table-body" class="divide-y divide-slate-100">
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-slate-500">Cargando...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="stock-pagination" class="px-4 py-3 border-t border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <div class="text-xs text-slate-600" id="stock-pagination-info"></div>
                    <div class="flex items-center gap-2">
                        <label for="page-size" class="text-xs text-slate-600">Filas</label>
                        <select id="page-size" class="border rounded px-2 py-1 text-xs">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                        </select>
                        <button id="page-prev" type="button" class="px-2 py-1 rounded border text-xs hover:bg-slate-50">Anterior</button>
                        <div id="page-numbers" class="flex items-center gap-1"></div>
                        <button id="page-next" type="button" class="px-2 py-1 rounded border text-xs hover:bg-slate-50">Siguiente</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('inventario.stock.modals.increase')
    @include('inventario.stock.modals.decrease')
    @include('inventario.stock.modals.adjust')

<script>
let DATA = [];
let seleccion = null;
const HISTORIAL_URL = "{{ route('inventario.historial') }}";
let currentPage = 1;
let pageSize = 20;
let totalRecords = 0;
let totalPages = 1;
let filtrosDebounce = null;

document.addEventListener("DOMContentLoaded", () => {
    cargarStock();

    document.getElementById("buscar").addEventListener("input", aplicarFiltros);
    document.getElementById("filter-bodega").addEventListener("change", () => aplicarFiltros({ immediate: true }));
    document.getElementById("filter-categoria").addEventListener("change", () => aplicarFiltros({ immediate: true }));
    document.getElementById("filter-low").addEventListener("change", () => aplicarFiltros({ immediate: true }));
    document.getElementById("page-size").addEventListener("change", (e) => {
        pageSize = Number(e.target.value) || 20;
        currentPage = 1;
        cargarStock();
    });
    document.getElementById("page-prev").addEventListener("click", () => {
        if (currentPage > 1) {
            currentPage -= 1;
            cargarStock();
        }
    });
    document.getElementById("page-next").addEventListener("click", () => {
        if (currentPage < totalPages) {
            currentPage += 1;
            cargarStock();
        }
    });
});

function cargarStock(preselectId = null) {
    const q = (document.getElementById("buscar").value || "").trim();
    const bodegaId = document.getElementById("filter-bodega").value || "";
    const categoria = document.getElementById("filter-categoria").value || "";
    const onlyLow = document.getElementById("filter-low").checked ? "1" : "0";

    const params = new URLSearchParams();
    params.set("paginated", "1");
    params.set("page", String(currentPage));
    params.set("per_page", String(pageSize));
    params.set("only_low", onlyLow);
    if (q) params.set("q", q);
    if (bodegaId) params.set("bodega_id", bodegaId);
    if (categoria) params.set("categoria", categoria);

    fetch(`/inventario/list?${params.toString()}`)
        .then(r => r.json())
        .then(payload => {
            DATA = Array.isArray(payload?.data) ? payload.data : [];
            totalRecords = Number(payload?.meta?.total || 0);
            currentPage = Number(payload?.meta?.current_page || 1);
            totalPages = Math.max(1, Number(payload?.meta?.last_page || 1));

            renderBodegas(payload?.filters?.bodegas || []);
            renderCategorias(payload?.filters?.categorias || []);
            renderTablaPaginada();

            if (preselectId) {
                const found = DATA.find(x => Number(x.id) === Number(preselectId));
                if (found) seleccion = found;
            }
        })
        .catch(() => {
            DATA = [];
            totalRecords = 0;
            totalPages = 1;
            currentPage = 1;
            renderTablaPaginada();
        });
}

function renderBodegas(lista) {
    const sel = document.getElementById("filter-bodega");
    const selected = sel.value || "";

    sel.innerHTML = '<option value="">Todas las bodegas</option>';
    lista.forEach((item) => {
        const opt = document.createElement("option");
        opt.value = String(item.id);
        opt.textContent = item.nombre;
        sel.appendChild(opt);
    });

    if ([...sel.options].some(o => o.value === selected)) {
        sel.value = selected;
    }
}

function renderCategorias(lista) {
    const sel = document.getElementById("filter-categoria");
    const selected = sel.value || "";

    sel.innerHTML = '<option value="">Todas las categorias</option>';
    lista.forEach((cat) => {
        const opt = document.createElement("option");
        opt.value = cat;
        opt.textContent = cat;
        sel.appendChild(opt);
    });

    if ([...sel.options].some(o => o.value === selected)) {
        sel.value = selected;
    }
}

function aplicarFiltros(options = {}) {
    if (options.immediate === true) {
        if (filtrosDebounce) clearTimeout(filtrosDebounce);
        currentPage = 1;
        cargarStock();
        return;
    }

    if (filtrosDebounce) clearTimeout(filtrosDebounce);
    filtrosDebounce = setTimeout(() => {
        currentPage = 1;
        cargarStock();
    }, 250);
}

function getPageWindow(current, total) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);

    const pages = [1];
    let start = Math.max(2, current - 2);
    let end = Math.min(total - 1, current + 2);

    while ((end - start + 1) < 5 && start > 2) start--;
    while ((end - start + 1) < 5 && end < total - 1) end++;

    if (start > 2) pages.push("...");
    for (let i = start; i <= end; i++) pages.push(i);
    if (end < total - 1) pages.push("...");
    pages.push(total);

    return pages;
}

function renderTablaPaginada() {
    renderTabla(DATA);
    renderPaginacion();
}

function renderPaginacion() {
    const info = document.getElementById("stock-pagination-info");
    const prev = document.getElementById("page-prev");
    const next = document.getElementById("page-next");
    const numbers = document.getElementById("page-numbers");

    if (!totalRecords) {
        info.textContent = "0 registros";
    } else {
        const start = (currentPage - 1) * pageSize + 1;
        const end = Math.min(currentPage * pageSize, totalRecords);
        info.textContent = `Mostrando ${start}-${end} de ${totalRecords} registros`;
    }

    prev.disabled = currentPage <= 1;
    next.disabled = currentPage >= totalPages;
    prev.classList.toggle("opacity-50", prev.disabled);
    next.classList.toggle("opacity-50", next.disabled);

    let html = "";
    for (const pageItem of getPageWindow(currentPage, totalPages)) {
        if (pageItem === "...") {
            html += `<span class="px-2 py-1 text-xs text-slate-500">...</span>`;
            continue;
        }
        const p = Number(pageItem);
        const active = p === currentPage
            ? "bg-blue-600 text-white border-blue-600"
            : "bg-white text-slate-700 border-slate-300 hover:bg-slate-50";
        html += `<button type="button" onclick="goToPage(${p})" class="px-2 py-1 rounded border text-xs ${active}">${p}</button>`;
    }
    numbers.innerHTML = html;
}

function goToPage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    cargarStock();
}

function renderTabla(lista) {
    const tbody = document.getElementById("stock-table-body");

    if (!lista.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="px-4 py-6 text-center text-slate-500">
                    No hay registros para los filtros aplicados.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = lista.map(item => {
        const minimo = Number(item.producto?.stock_minimo ?? 0);
        const actual = Number(item.stock_actual ?? 0);
        const esBajo = actual < minimo;
        const estadoClass = esBajo
            ? "bg-red-100 text-red-700"
            : "bg-green-100 text-green-700";
        const estadoText = esBajo ? "Bajo" : "Normal";
        const rowClass = esBajo ? "bg-red-50" : "";

        return `
            <tr class="${rowClass}">
                <td class="px-4 py-3 text-slate-800">${escapeHtml(item.producto?.nombre ?? "N/D")}</td>
                <td class="px-4 py-3 text-slate-600">${escapeHtml(item.producto?.codigo_interno ?? "-")}</td>
                <td class="px-4 py-3 text-slate-700">${escapeHtml(item.bodega?.nombre ?? "N/D")}</td>
                <td class="px-4 py-3 text-slate-700">${escapeHtml(item.percha?.codigo ?? "-")}</td>
                <td class="px-4 py-3 text-right font-semibold ${esBajo ? "text-red-700" : "text-slate-900"}">${actual}</td>
                <td class="px-4 py-3 text-right text-slate-700">${minimo}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded text-xs font-semibold ${estadoClass}">${estadoText}</span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="openIncreaseById(${item.id})" class="px-2 py-1 rounded bg-green-600 text-white text-xs hover:bg-green-700">Aumentar</button>
                        <button type="button" onclick="openDecreaseById(${item.id})" class="px-2 py-1 rounded bg-red-600 text-white text-xs hover:bg-red-700">Disminuir</button>
                        <button type="button" onclick="openAdjustById(${item.id})" class="px-2 py-1 rounded bg-indigo-600 text-white text-xs hover:bg-indigo-700">Ajustar</button>
                        <button type="button" onclick="goToHistoryById(${item.id})" class="px-2 py-1 rounded bg-slate-700 text-white text-xs hover:bg-slate-800">Ver movimientos</button>
                    </div>
                </td>
            </tr>
        `;
    }).join("");
}

function setSeleccionById(id) {
    const found = DATA.find(x => Number(x.id) === Number(id));
    if (!found) return false;
    seleccion = found;
    return true;
}

function openIncreaseById(id) {
    if (!setSeleccionById(id)) return;
    openIncrease();
}

function openDecreaseById(id) {
    if (!setSeleccionById(id)) return;
    openDecrease();
}

function openAdjustById(id) {
    if (!setSeleccionById(id)) return;
    openAdjust();
}

function goToHistoryById(id) {
    if (!setSeleccionById(id)) return;
    goToHistory();
}

function openIncrease() {
    if (!seleccion) return;
    document.getElementById("increase-cantidad").value = "";
    document.getElementById("modal-increase").classList.remove("hidden");
}

function openDecrease() {
    if (!seleccion) return;
    document.getElementById("decrease-cantidad").value = "";
    document.getElementById("current-stock").innerText = seleccion.stock_actual;
    document.getElementById("modal-decrease").classList.remove("hidden");
}

function openAdjust() {
    if (!seleccion) return;
    document.getElementById("adjust-current").innerText = seleccion.stock_actual;
    document.getElementById("adjust-nuevo").value = seleccion.stock_actual;
    document.getElementById("adjust-motivo").value = "";
    document.getElementById("modal-adjust").classList.remove("hidden");
}

function closeIncrease() {
    document.getElementById("modal-increase").classList.add("hidden");
}

function closeDecrease() {
    document.getElementById("modal-decrease").classList.add("hidden");
}

function closeAdjust() {
    document.getElementById("modal-adjust").classList.add("hidden");
}

function submitAdjust(e) {
    e.preventDefault();
    if (!seleccion) return;

    const nuevoStock = parseInt(document.getElementById("adjust-nuevo").value, 10);
    const motivo = document.getElementById("adjust-motivo").value || null;

    if (isNaN(nuevoStock) || nuevoStock < 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Dato invalido',
            text: 'El nuevo stock debe ser un numero entero mayor o igual a 0.'
        });
        return;
    }

    Swal.fire({
        title: 'Guardando ajuste...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/inventario/adjust`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            producto_id: seleccion.producto_id,
            bodega_id: seleccion.bodega_id,
            percha_id: seleccion.percha_id,
            nuevo_stock: nuevoStock,
            motivo: motivo
        })
    })
    .then(r => {
        if (!r.ok) return r.json().then(j => { throw j; });
        return r.json();
    })
    .then((resp) => {
        Swal.fire({
            icon: 'success',
            title: 'Ajuste guardado',
            text: resp.message || 'El stock se actualizo correctamente.',
            timer: 2200,
            showConfirmButton: false
        });

        const selectedId = seleccion?.id;
        closeAdjust();
        cargarStock(selectedId);
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message || 'Error al ajustar el stock.'
        });
    });
}

function submitIncrease(e) {
    e.preventDefault();
    if (!seleccion) return;

    const cantidad = parseInt(document.getElementById("increase-cantidad").value, 10);
    if (isNaN(cantidad) || cantidad < 1) {
        Swal.fire({
            icon: 'warning',
            title: 'Dato invalido',
            text: 'La cantidad debe ser un entero mayor o igual a 1.'
        });
        return;
    }

    Swal.fire({
        title: 'Actualizando stock...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

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
            cantidad: cantidad
        })
    })
    .then(r => {
        if (!r.ok) return r.json().then(j => { throw j; });
        return r.json();
    })
    .then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Stock aumentado',
            text: 'El stock se aumento correctamente.',
            timer: 2000,
            showConfirmButton: false
        });

        const selectedId = seleccion?.id;
        closeIncrease();
        cargarStock(selectedId);
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message || 'Error al aumentar el stock.'
        });
    });
}

function submitDecrease(e) {
    e.preventDefault();
    if (!seleccion) return;

    const cantidad = parseInt(document.getElementById("decrease-cantidad").value, 10);
    if (isNaN(cantidad) || cantidad < 1) {
        Swal.fire({
            icon: 'warning',
            title: 'Dato invalido',
            text: 'La cantidad debe ser un entero mayor o igual a 1.'
        });
        return;
    }

    Swal.fire({
        title: 'Actualizando stock...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

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
            cantidad: cantidad
        })
    })
    .then(r => {
        if (!r.ok) return r.json().then(j => { throw j; });
        return r.json();
    })
    .then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Stock disminuido',
            text: 'El stock se disminuyo correctamente.',
            timer: 2000,
            showConfirmButton: false
        });

        const selectedId = seleccion?.id;
        closeDecrease();
        cargarStock(selectedId);
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message || 'Error al disminuir el stock.'
        });
    });
}

function goToHistory() {
    if (!seleccion) return;

    const params = new URLSearchParams({
        producto_id: seleccion.producto_id,
        bodega_id: seleccion.bodega_id,
        percha_id: seleccion.percha_id || '',
        producto_nombre: seleccion.producto?.nombre || '',
        bodega_nombre: seleccion.bodega?.nombre || '',
        percha_codigo: seleccion.percha?.codigo || ''
    });

    window.location.href = `${HISTORIAL_URL}?${params.toString()}`;
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>
</x-app-layout>
