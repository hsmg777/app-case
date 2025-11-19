<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">

            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Productos') }}
            </h2>

            <button 
                onclick="history.back()"
                class="text-blue-700 hover:text-blue-900 transition flex items-center space-x-1"
                title="Regresar"
            >   
                <x-heroicon-s-arrow-left class="w-5 h-5" />
                <span>Atrás</span>
            </button>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">

            <!-- BOTÓN CREAR -->
            <div class="flex justify-end mb-4">
                <button 
                    onclick="openCreateModal()"
                    class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-md shadow">
                    + Nuevo Producto
                </button>
            </div>
            <!-- FILTROS -->
            <div class="flex flex-col md:flex-row md:items-center md:space-x-4 mb-4">

                <!-- BUSCADOR -->
                <input 
                    id="buscar-input"
                    type="text"
                    placeholder="Buscar por nombre o código..."
                    class="border px-3 py-2 rounded w-full md:w-1/3"
                    oninput="aplicarFiltros()"
                >

                <!-- SELECT CATEGORÍAS -->
                <select 
                    id="categoria-select"
                    class="border px-3 py-2 rounded w-full md:w-1/4 mt-3 md:mt-0"
                    onchange="aplicarFiltros()"
                >
                    <option value="">Todas las categorías</option>
                </select>
            </div>
            <!-- TABLA -->
            <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
                <table class="min-w-full">
                    <thead class="bg-blue-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-blue-900">Nombre</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-blue-900">Código interno</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-blue-900">Categoría</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-blue-900">Stock mínimo</th>
                            <th class="px-4 py-2 text-center text-sm font-semibold text-blue-900">Acciones</th>
                        </tr>
                    </thead>

                    <tbody id="tabla-productos"></tbody>
                </table>
            </div>
            <!-- Paginación -->
            <div id="paginacion" class="flex justify-center mt-4 space-x-2"></div>

        </div>
    </div>

    <!-- MODALES -->
    @include('inventario.productos.modals.assign')
    @include('inventario.productos.modals.create')
    @include('inventario.productos.modals.edit')

    <!-- JS CRUD -->
   <script>
        const CSRF_TOKEN = "{{ csrf_token() }}";

        // ==============================
        // Helpers
        // ==============================
        const toInt = (v) => (v !== "" && v !== null ? parseInt(v, 10) : null);
        const toFloat = (v) => (v !== "" && v !== null ? parseFloat(v) : null);
        const orNull = (v) => (v === "" ? null : v);

        function swalSuccess(title = "Listo", text = "") {
            return Swal.fire({ icon: "success", title, text, timer: 1500, showConfirmButton: false });
        }
        function swalError(title = "Error", text = "Intenta nuevamente") {
            return Swal.fire({ icon: "error", title, text });
        }
        async function swalConfirm(text = "¿Estás seguro?") {
            const { isConfirmed } = await Swal.fire({
                icon: "warning",
                title: "Confirmar",
                text,
                showCancelButton: true,
                confirmButtonText: "Sí",
                cancelButtonText: "Cancelar",
            });
            return isConfirmed;
        }
        async function swalLoading(promise, text = "Procesando…") {
            Swal.fire({ title: text, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const result = await promise;
                Swal.close();
                return result;
            } catch (e) {
                Swal.close();
                throw e;
            }
        }

        // ==============================
        // Variables Globales
        // ==============================
        let PRODUCTOS = [];
        let PRODUCTOS_FILTRADOS = [];
        let PAGINA_ACTUAL = 1;
        const ITEMS_POR_PAGINA = 10;

        // ==============================
        // Init
        // ==============================
        document.addEventListener("DOMContentLoaded", () => {
            cargarProductos();

            document.getElementById("form-create-product")?.addEventListener("submit", handleCreateProduct);
            document.getElementById("form-edit")?.addEventListener("submit", handleEditProduct);
        });

        // ==============================
        // Cargar Productos
        // ==============================
        function cargarProductos() {
            fetch("/productos/list")
                .then((res) => res.json())
                .then((data) => {
                    PRODUCTOS = data;
                    PRODUCTOS_FILTRADOS = data;

                    cargarCategoriasUnicas(data);
                    renderPagina();
                });
        }

        // ==============================
        // Render Tabla
        // ==============================
        function renderTabla(lista) {
            let rows = "";

            lista.forEach((p) => {
                rows += `
                    <tr class="border-b">
                        <td class="px-4 py-2">${p.nombre}</td>
                        <td class="px-4 py-2">${p.codigo_interno ?? "-"}</td>
                        <td class="px-4 py-2">${p.categoria ?? "-"}</td>
                        <td class="px-4 py-2">${p.stock_minimo}</td>
                        <td class="px-4 py-2 text-center">
                            <button onclick="openAssignModal(${p.id})" class="text-green-600 hover:underline mr-3">
                                Asignar Stock
                            </button>
                            <button onclick="openEditModal(${p.id})" class="text-blue-600 hover:underline mr-3">Editar</button>
                            <button onclick="eliminarProducto(${p.id})" class="text-red-600 hover:underline">Eliminar</button>
                        </td>
                    </tr>`;
            });

            document.getElementById("tabla-productos").innerHTML = rows;
        }

        // ==============================
        // Paginación
        // ==============================
        function renderPagina() {
            const inicio = (PAGINA_ACTUAL - 1) * ITEMS_POR_PAGINA;
            const fin = inicio + ITEMS_POR_PAGINA;

            const paginaItems = PRODUCTOS_FILTRADOS.slice(inicio, fin);

            renderTabla(paginaItems);
            renderControlesPaginacion();
        }

        function renderControlesPaginacion() {
            const totalPaginas = Math.ceil(PRODUCTOS_FILTRADOS.length / ITEMS_POR_PAGINA);
            const cont = document.getElementById("paginacion");

            if (totalPaginas <= 1) {
                cont.innerHTML = "";
                return;
            }

            let html = "";

            // Botón anterior
            html += `
                <button 
                    class="px-3 py-1 border rounded ${PAGINA_ACTUAL === 1 ? 'opacity-50 cursor-not-allowed' : ''}"
                    onclick="cambiarPagina(${PAGINA_ACTUAL - 1})"
                    ${PAGINA_ACTUAL === 1 ? 'disabled' : ''}
                >Anterior</button>
            `;

            // Números
            for (let i = 1; i <= totalPaginas; i++) {
                html += `
                    <button 
                        class="px-3 py-1 border rounded ${i === PAGINA_ACTUAL ? 'bg-blue-600 text-white' : ''}"
                        onclick="cambiarPagina(${i})"
                    >${i}</button>
                `;
            }

            // Botón siguiente
            html += `
                <button 
                    class="px-3 py-1 border rounded ${PAGINA_ACTUAL === totalPaginas ? 'opacity-50 cursor-not-allowed' : ''}"
                    onclick="cambiarPagina(${PAGINA_ACTUAL + 1})"
                    ${PAGINA_ACTUAL === totalPaginas ? 'disabled' : ''}
                >Siguiente</button>
            `;

            cont.innerHTML = html;
        }

        function cambiarPagina(num) {
            PAGINA_ACTUAL = num;
            renderPagina();
        }

        // ==============================
        // Filtros
        // ==============================
        function cargarCategoriasUnicas(data) {
            const select = document.getElementById("categoria-select");

            select.innerHTML = `<option value="">Todas las categorías</option>`;

            const categorias = [...new Set(data.map(p => p.categoria).filter(c => c))];

            categorias.forEach(cat => {
                const opt = document.createElement("option");
                opt.value = cat;
                opt.textContent = cat;
                select.appendChild(opt);
            });
        }

        function aplicarFiltros() {
            const texto = document.getElementById("buscar-input").value.toLowerCase();
            const categoria = document.getElementById("categoria-select").value;

            let filtrados = PRODUCTOS;

            if (texto.trim() !== "") {
                filtrados = filtrados.filter(p =>
                    p.nombre.toLowerCase().includes(texto) ||
                    (p.codigo_interno ?? "").toLowerCase().includes(texto)
                );
            }

            if (categoria !== "") {
                filtrados = filtrados.filter(p => p.categoria === categoria);
            }

            PRODUCTOS_FILTRADOS = filtrados;
            PAGINA_ACTUAL = 1;

            renderPagina();
        }

        // ==============================
        // Crear Producto
        // ==============================
        async function handleCreateProduct(e) {
            e.preventDefault();
            const fd = new FormData(e.target);

            const productPayload = {
                nombre: fd.get("nombre"),
                descripcion: orNull(fd.get("descripcion")),
                codigo_barras: orNull(fd.get("codigo_barras")),
                codigo_interno: orNull(fd.get("codigo_interno")),
                categoria: orNull(fd.get("categoria")),
                unidad_medida: fd.get("unidad_medida"),
                stock_minimo: toInt(fd.get("stock_minimo")),
                estado: true,
            };

            try {
                const resProd = await fetch("/productos/store", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(productPayload)
                });


                if (!resProd.ok) throw new Error("Error al crear producto");

                const producto = await resProd.json();

                const pricePayload = {
                    producto_id: producto.id,
                    precio_unitario: toFloat(fd.get("precio_unitario")),
                    moneda: fd.get("moneda"),
                    precio_por_cantidad: toFloat(fd.get("precio_por_cantidad")),
                    cantidad_min: toInt(fd.get("cantidad_min")),
                    cantidad_max: toInt(fd.get("cantidad_max")),
                    precio_por_caja: toFloat(fd.get("precio_por_caja")),
                    unidades_por_caja: toInt(fd.get("unidades_por_caja")),
                };

                const resPrice = await fetch("/producto-precios", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(pricePayload),
                });


                if (!resPrice.ok) throw new Error("Error al crear precios");

                closeCreateModal();
                e.target.reset();

                await swalSuccess("Guardado", "Producto creado correctamente");

                cargarProductos();

            } catch (err) {
                swalError("Error al guardar", err.message);
            }
        }

        // ==============================
        // Editar Producto
        // ==============================
        async function openEditModal(id) {
            try {
                const [resProd, resPrice] = await Promise.all([
                    fetch(`/productos/show/${id}`),
                    fetch(`/producto-precios/producto/${id}`),
                ]);

                const p = await resProd.json();
                let price = null;

                try { price = await resPrice.json(); } catch {}

                document.getElementById("edit-id").value = p.id;
                document.getElementById("edit-nombre").value = p.nombre ?? "";
                document.getElementById("edit-descripcion").value = p.descripcion ?? "";
                document.getElementById("edit-codigo-barras").value = p.codigo_barras ?? "";
                document.getElementById("edit-codigo-interno").value = p.codigo_interno ?? "";
                document.getElementById("edit-categoria").value = p.categoria ?? "";
                document.getElementById("edit-unidad-medida").value = p.unidad_medida ?? "";
                document.getElementById("edit-stock-minimo").value = p.stock_minimo ?? 0;

                document.getElementById("edit-price-id").value = price?.id ?? "";
                document.getElementById("edit-precio-unitario").value = price?.precio_unitario ?? "";
                document.getElementById("edit-moneda").value = price?.moneda ?? "USD";
                document.getElementById("edit-cantidad-min").value = price?.cantidad_min ?? "";
                document.getElementById("edit-cantidad-max").value = price?.cantidad_max ?? "";
                document.getElementById("edit-precio-por-cantidad").value = price?.precio_por_cantidad ?? "";
                document.getElementById("edit-unidades-por-caja").value = price?.unidades_por_caja ?? "";
                document.getElementById("edit-precio-por-caja").value = price?.precio_por_caja ?? "";

                if (typeof window._editToggleApply === "function") {
                    window._editToggleApply();
                }

                document.getElementById("modal-edit").classList.remove("hidden");

            } catch (err) {
                swalError("Error", "No se pudo cargar el producto");
            }
        }

        function closeEditModal() {
            document.getElementById("modal-edit").classList.add("hidden");
        }

        async function handleEditProduct(e) {
            e.preventDefault();

            const fd = new FormData(e.target);
            const id = fd.get("id");
            const priceId = fd.get("price_id");

            const productPayload = {
                nombre: fd.get("nombre"),
                descripcion: orNull(fd.get("descripcion")),
                codigo_barras: orNull(fd.get("codigo_barras")),
                codigo_interno: orNull(fd.get("codigo_interno")),
                categoria: orNull(fd.get("categoria")),
                unidad_medida: fd.get("unidad_medida"),
                stock_minimo: toInt(fd.get("stock_minimo")),
            };

            try {
                await swalLoading(
                    fetch(`/productos/update/${id}`, {
                        method: "PUT",
                        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": CSRF_TOKEN },
                        body: JSON.stringify(productPayload),
                    }),
                    "Actualizando producto…"
                );

                const pricePayload = {
                    producto_id: id,
                    precio_unitario: toFloat(fd.get("precio_unitario")),
                    moneda: fd.get("moneda"),
                    precio_por_cantidad: toFloat(fd.get("precio_por_cantidad")),
                    cantidad_min: toInt(fd.get("cantidad_min")),
                    cantidad_max: toInt(fd.get("cantidad_max")),
                    precio_por_caja: toFloat(fd.get("precio_por_caja")),
                    unidades_por_caja: toInt(fd.get("unidades_por_caja")),
                };

                let url = "/producto-precios";
                let method = "POST";

                if (priceId) {
                    url = `/producto-precios/${priceId}`;
                    method = "PUT";
                }

                const resPrice = await swalLoading(
                    fetch(url, {
                        method,
                        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": CSRF_TOKEN },
                        body: JSON.stringify(pricePayload),
                    }),
                    "Guardando precios…"
                );

                if (!resPrice.ok) throw new Error("Error al guardar precios");

                closeEditModal();
                await swalSuccess("Actualizado", "Producto actualizado");

                cargarProductos();

            } catch (err) {
                swalError("Error al actualizar", err.message);
            }
        }

        // ==============================
        // Eliminar producto
        // ==============================
        async function eliminarProducto(id) {
            const ok = await swalConfirm("¿Eliminar este producto?");
            if (!ok) return;

            try {
                const res = await swalLoading(
                    fetch(`/productos/delete/${id}`, {
                        method: "DELETE",
                        headers: { "X-CSRF-TOKEN": CSRF_TOKEN },
                    }),
                    "Eliminando…"
                );

                const data = await res.json();
                await swalSuccess("Eliminado", data.message);
                cargarProductos();

            } catch (err) {
                swalError("Error al eliminar", err.message);
            }
        }
        // ==================================
        // MODAL ASIGNAR STOCK
        // ==================================

        async function openAssignModal(productoId) {

            document.getElementById("assign-producto-id").value = productoId;

            // Cargar bodegas
            const bodegasRes = await fetch("/inventario/bodegas");
            const bodegas = await bodegasRes.json();

            let bodegaSelect = document.getElementById("assign-bodega");
            bodegaSelect.innerHTML = `<option value="">Seleccione...</option>`;
            bodegas.forEach(b => {
                bodegaSelect.innerHTML += `<option value="${b.id}">${b.nombre}</option>`;
            });

            // Cargar perchas
            const perchasRes = await fetch("/inventario/perchas");
            const perchas = await perchasRes.json();

            let perchaSelect = document.getElementById("assign-percha");
            perchaSelect.innerHTML = `<option value="">Seleccione...</option>`;
            perchas.forEach(p => {
                perchaSelect.innerHTML += `<option value="${p.id}">${p.codigo}</option>`;
            });

            // Mostrar modal
            document.getElementById("modal-assign").classList.remove("hidden");
        }


        function closeAssignModal() {
            document.getElementById("modal-assign").classList.add("hidden");
        }

        async function submitAssign(e) {
            e.preventDefault();

            const payload = {
                producto_id: document.getElementById("assign-producto-id").value,
                bodega_id: document.getElementById("assign-bodega").value,
                percha_id: document.getElementById("assign-percha").value,
                stock_actual: parseInt(document.getElementById("assign-stock").value),
                stock_reservado: 0
            };

            try {
                await swalLoading(
                    fetch("/inventario/store", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: JSON.stringify(payload)
                    }),
                    "Asignando producto…"
                );

                closeAssignModal();
                await swalSuccess("Asignado", "El producto fue asignado correctamente");

            } catch (err) {
                swalError("Error", "No se pudo asignar stock");
            }
        }
        </script>

</x-app-layout>
