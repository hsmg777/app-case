<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Productos') }}
            </h2>

            <button
                onclick="window.location.href='{{ route('inventario.index') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center space-x-1"
                title="Regresar"
            >
                <x-heroicon-s-arrow-left class="w-5 h-5" />
                <span>Atrás</span>
            </button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <section class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-5">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <p class="text-sm text-slate-500">Gestiona tu catálogo e importaciones</p>
                        <div class="mt-2 inline-flex items-center gap-2 text-xs font-medium text-blue-800 bg-blue-50 border border-blue-100 rounded-full px-3 py-1">
                            <span>Productos visibles</span>
                            <span id="products-count" class="bg-white text-blue-900 border border-blue-200 rounded-full px-2 py-0.5">0</span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                        <div class="grid grid-cols-2 sm:flex gap-2">
                            <button
                                id="btn-download-template"
                                type="button"
                                class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2.5 rounded-lg shadow-sm font-medium"
                            >
                                Plantilla XLSX
                            </button>
                            <button
                                id="btn-import-products"
                                type="button"
                                class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-lg shadow-sm font-medium"
                            >
                                Carga Masiva
                            </button>
                            <button
                                id="btn-export-excel"
                                type="button"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg shadow-sm font-medium"
                            >
                                Exportar Excel
                            </button>
                        </div>

                        <button
                            id="btn-open-create"
                            type="button"
                            class="bg-blue-700 hover:bg-blue-800 text-white px-5 py-2.5 rounded-lg shadow-sm font-semibold whitespace-nowrap"
                        >
                            + Nuevo Producto
                        </button>
                        <input id="input-import-products" type="file" accept=".xlsx" class="hidden" />
                    </div>
                </div>
            </section>

            <section class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-5">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-5">
                        <input
                            id="buscar-input"
                            type="text"
                            placeholder="Buscar por nombre o código..."
                            class="border border-slate-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2.5 rounded-lg w-full"
                            oninput="aplicarFiltros()"
                        >
                    </div>

                    <div class="md:col-span-4">
                        <select
                            id="categoria-select"
                            class="border border-slate-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2.5 rounded-lg w-full"
                            onchange="aplicarFiltros()"
                        >
                            <option value="">Todas las categorías</option>
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <select
                          id="estado-select"
                          class="border border-slate-300 focus:border-blue-500 focus:ring-blue-500 px-3 py-2.5 rounded-lg w-full"
                        >
                            <option value="activos" selected>Activos</option>
                            <option value="inactivos">Inactivos</option>
                            <option value="todos">Todos</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="bg-white shadow-sm rounded-xl overflow-hidden border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="px-5 py-3 text-left text-sm font-semibold text-slate-700">Nombre</th>
                                <th class="px-5 py-3 text-left text-sm font-semibold text-slate-700">Código interno</th>
                                <th class="px-5 py-3 text-left text-sm font-semibold text-slate-700">Categoría</th>
                                <th class="px-5 py-3 text-left text-sm font-semibold text-slate-700">Stock mínimo</th>
                                <th class="px-5 py-3 text-center text-sm font-semibold text-slate-700">Acciones</th>
                            </tr>
                        </thead>

                        <tbody id="tabla-productos"></tbody>
                    </table>
                </div>
            </section>

            <div id="paginacion" class="flex flex-wrap justify-center gap-2"></div>
        </div>
    </div>

    @include('inventario.productos.modals.create')
    @include('inventario.productos.modals.edit')

    <script>
    (() => {
      const CSRF_TOKEN =
        document.querySelector('meta[name="csrf-token"]')?.content || "{{ csrf_token() }}";

      const $ = (id) => document.getElementById(id);

      const toInt = (v) => (v !== "" && v !== null && v !== undefined ? parseInt(v, 10) : null);
      const toFloat = (v) => (v !== "" && v !== null && v !== undefined ? parseFloat(v) : null);
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

      async function readJsonSafe(res) {
        const t = await res.text();
        try { return t ? JSON.parse(t) : null; } catch { return t || null; }
      }

      let PRODUCTOS = [];
      let PRODUCTOS_FILTRADOS = [];
      let PAGINA_ACTUAL = 1;
      const ITEMS_POR_PAGINA = 10;

      function openCreateModal() {
        const el = $("modal-create");
        if (!el) return console.error("No existe #modal-create");
        el.classList.remove("hidden");
        el.classList.add("flex");
        el.style.display = "flex";
      }

      function closeCreateModal() {
        const el = $("modal-create");
        if (!el) return;
        el.classList.add("hidden");
        el.classList.remove("flex");
        el.style.display = "none";
      }

      document.addEventListener("DOMContentLoaded", () => {
        cargarProductos();
        $("estado-select")?.addEventListener("change", () => {
          cargarProductos(); 
        });

        const btn = $("btn-open-create");
        if (btn) {
          btn.addEventListener("click", (ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            openCreateModal();
          });
        }

        const exportBtn = $("btn-export-excel");
        if (exportBtn) {
          exportBtn.addEventListener("click", (ev) => {
            ev.preventDefault();
            exportarProductosExcel();
          });
        }

        const templateBtn = $("btn-download-template");
        if (templateBtn) {
          templateBtn.addEventListener("click", (ev) => {
            ev.preventDefault();
            window.location.href = "{{ route('productos.import.template') }}";
          });
        }

        const importBtn = $("btn-import-products");
        const importInput = $("input-import-products");
        if (importBtn && importInput) {
          importBtn.addEventListener("click", (ev) => {
            ev.preventDefault();
            importInput.click();
          });

          importInput.addEventListener("change", async (ev) => {
            const file = ev.target.files?.[0];
            if (!file) return;
            try {
              await subirExcelProductos(file);
            } finally {
              importInput.value = "";
            }
          });
        }

        $("form-create-product")?.addEventListener("submit", handleCreateProduct);
        $("form-edit")?.addEventListener("submit", handleEditProduct);

        document.querySelectorAll('[data-create-close]').forEach((btn) => {
          btn.addEventListener('click', (ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            closeCreateModal();
          });
        });

        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') closeCreateModal();
        });
      });

      async function cargarProductos() {
        try {
          const estadoFiltro = $("estado-select")?.value || "activos";
          const res = await fetch(`/productos/list?estado=${encodeURIComponent(estadoFiltro)}`);
          if (!res.ok) throw new Error("No se pudo cargar /productos/list");
          const data = await res.json();

          PRODUCTOS = Array.isArray(data) ? data : [];
          PRODUCTOS_FILTRADOS = PRODUCTOS;

          cargarCategoriasUnicas(PRODUCTOS);
          aplicarFiltros(); 
        } catch (e) {
          console.error(e);
          swalError("Error", "No se pudieron cargar los productos");
        }
      }


      function renderTabla(lista) {
        let rows = "";

        if (!lista.length) {
          $("tabla-productos").innerHTML = `
            <tr>
              <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">
                No hay productos para los filtros seleccionados.
              </td>
            </tr>
          `;
          return;
        }

        lista.forEach((p) => {
          const activo = (p.estado === true || p.estado === 1 || p.estado === "1");
          const accionText = activo ? "Desactivar" : "Activar";
          const accionClass = activo
            ? "border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100"
            : "border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100";
          const confirmText = activo
            ? "¿Desactivar este producto? No se borrará el historial."
            : "¿Activar este producto?";

          rows += `
            <tr class="border-b border-slate-100 ${activo ? "" : "bg-slate-50/70"}">
              <td class="px-5 py-3 text-slate-800 font-medium">${p.nombre ?? "-"}</td>
              <td class="px-5 py-3 text-slate-700">${p.codigo_interno ?? "-"}</td>
              <td class="px-5 py-3 text-slate-700">${p.categoria ?? "-"}</td>
              <td class="px-5 py-3 text-slate-700">${p.stock_minimo ?? 0}</td>
              <td class="px-5 py-3">
                <div class="flex items-center justify-center gap-2">
                <button onclick="openEditModal(${p.id})" class="px-3 py-1.5 rounded-md border border-blue-200 bg-blue-50 text-blue-700 text-sm font-medium hover:bg-blue-100">
                  Editar
                </button>
                <button
                  onclick="cambiarEstadoProducto(${p.id}, ${activo ? "false" : "true"}, '${confirmText.replace(/'/g, "\\'")}')"
                  class="px-3 py-1.5 rounded-md text-sm font-semibold transition ${accionClass}"
                >
                  ${accionText}
                </button>
                </div>
              </td>
            </tr>`;
        });

        $("tabla-productos").innerHTML = rows;
      }

      function renderPagina() {
        const inicio = (PAGINA_ACTUAL - 1) * ITEMS_POR_PAGINA;
        const fin = inicio + ITEMS_POR_PAGINA;

        const paginaItems = PRODUCTOS_FILTRADOS.slice(inicio, fin);
        renderTabla(paginaItems);
        renderControlesPaginacion();
      }

      function renderControlesPaginacion() {
        const totalPaginas = Math.ceil(PRODUCTOS_FILTRADOS.length / ITEMS_POR_PAGINA);
        const cont = $("paginacion");

        if (!cont) return;
        if (totalPaginas <= 1) {
          cont.innerHTML = "";
          return;
        }

        let html = "";

        html += `
          <button
            class="px-3 py-1.5 border border-slate-300 rounded-md bg-white text-slate-700 ${PAGINA_ACTUAL === 1 ? "opacity-50 cursor-not-allowed" : "hover:bg-slate-50"}"
            onclick="cambiarPagina(${PAGINA_ACTUAL - 1})"
            ${PAGINA_ACTUAL === 1 ? "disabled" : ""}
          >Anterior</button>
        `;

        for (let i = 1; i <= totalPaginas; i++) {
          html += `
            <button
              class="px-3 py-1.5 border rounded-md ${
                i === PAGINA_ACTUAL
                  ? "bg-blue-700 border-blue-700 text-white"
                  : "bg-white border-slate-300 text-slate-700 hover:bg-slate-50"
              }"
              onclick="cambiarPagina(${i})"
            >${i}</button>
          `;
        }

        html += `
          <button
            class="px-3 py-1.5 border border-slate-300 rounded-md bg-white text-slate-700 ${PAGINA_ACTUAL === totalPaginas ? "opacity-50 cursor-not-allowed" : "hover:bg-slate-50"}"
            onclick="cambiarPagina(${PAGINA_ACTUAL + 1})"
            ${PAGINA_ACTUAL === totalPaginas ? "disabled" : ""}
          >Siguiente</button>
        `;

        cont.innerHTML = html;
      }

      function cambiarPagina(num) {
        const totalPaginas = Math.max(1, Math.ceil(PRODUCTOS_FILTRADOS.length / ITEMS_POR_PAGINA));
        PAGINA_ACTUAL = Math.min(Math.max(1, num), totalPaginas);
        renderPagina();
      }

      function cargarCategoriasUnicas(data) {
        const select = $("categoria-select");
        if (!select) return;

        select.innerHTML = `<option value="">Todas las categorías</option>`;

        const categorias = [...new Set(data.map((p) => p.categoria).filter((c) => c))];
        categorias.forEach((cat) => {
          const opt = document.createElement("option");
          opt.value = cat;
          opt.textContent = cat;
          select.appendChild(opt);
        });
      }

      function aplicarFiltros() {
        const texto = ($("buscar-input")?.value || "").trim().toLowerCase();
        const categoria = $("categoria-select")?.value || "";
        const estadoFiltro = $("estado-select")?.value || "activos";

        const norm = (v) => (v ?? "").toString().toLowerCase();
        const isActivo = (p) => (p.estado === true || p.estado === 1 || p.estado === "1");

        let filtrados = PRODUCTOS;

        if (texto !== "") {
          filtrados = filtrados.filter(
            (p) =>
              norm(p.nombre).includes(texto) ||
              norm(p.codigo_interno).includes(texto) ||
              norm(p.codigo_barras).includes(texto)
          );
        }

        if (categoria !== "") {
          filtrados = filtrados.filter((p) => p.categoria === categoria);
        }

        if (estadoFiltro === "activos") {
          filtrados = filtrados.filter((p) => isActivo(p));
        } else if (estadoFiltro === "inactivos") {
          filtrados = filtrados.filter((p) => !isActivo(p));
        }

        PRODUCTOS_FILTRADOS = filtrados;
        const countEl = $("products-count");
        if (countEl) countEl.textContent = String(filtrados.length);
        PAGINA_ACTUAL = 1;
        renderPagina();
      }

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
          iva_porcentaje: toFloat(fd.get("iva_porcentaje")) ?? 15,
          estado: true,
        };

        try {
          const resProd = await swalLoading(
            fetch("/productos/store", {
              method: "POST",
              headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": CSRF_TOKEN },
              body: JSON.stringify(productPayload),
            }),
            "Creando producto…"
          );

          if (!resProd.ok) {
            const err = await readJsonSafe(resProd);
            throw new Error(err?.message || "Error al crear producto");
          }

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

          const resPrice = await swalLoading(
            fetch("/producto-precios", {
              method: "POST",
              headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": CSRF_TOKEN },
              body: JSON.stringify(pricePayload),
            }),
            "Guardando precios…"
          );

          if (!resPrice.ok) {
            const err = await readJsonSafe(resPrice);
            throw new Error(err?.message || "Error al crear precios");
          }

          closeCreateModal();
          e.target.reset();

          await swalSuccess("Guardado", "Producto creado correctamente");
          cargarProductos();
        } catch (err) {
          console.error(err);
          swalError("Error al guardar", err.message || "Intenta nuevamente");
        }
      }

      async function openEditModal(id) {
        try {
          const [resProd, resPrice] = await Promise.all([
            fetch(`/productos/show/${id}`),
            fetch(`/producto-precios/producto/${id}`),
          ]);

          if (!resProd.ok) throw new Error("No se pudo cargar el producto");

          const p = await resProd.json();
          let price = null;
          if (resPrice.ok) {
            try { price = await resPrice.json(); } catch { price = null; }
          }

          $("edit-id").value = p.id;
          $("edit-nombre").value = p.nombre ?? "";
          $("edit-descripcion").value = p.descripcion ?? "";
          $("edit-codigo-barras").value = p.codigo_barras ?? "";
          $("edit-codigo-interno").value = p.codigo_interno ?? "";
          $("edit-categoria").value = p.categoria ?? "";
          $("edit-unidad-medida").value = p.unidad_medida ?? "";
          $("edit-stock-minimo").value = p.stock_minimo ?? 0;
          $("edit-iva-porcentaje").value = p.iva_porcentaje ?? 15;

          $("edit-price-id").value = price?.id ?? "";
          $("edit-precio-unitario").value = price?.precio_unitario ?? "";
          $("edit-moneda").value = price?.moneda ?? "USD";
          $("edit-cantidad-min").value = price?.cantidad_min ?? "";
          $("edit-cantidad-max").value = price?.cantidad_max ?? "";
          $("edit-precio-por-cantidad").value = price?.precio_por_cantidad ?? "";
          $("edit-unidades-por-caja").value = price?.unidades_por_caja ?? "";
          $("edit-precio-por-caja").value = price?.precio_por_caja ?? "";

          if (typeof window._editToggleApply === "function") window._editToggleApply();
          $("modal-edit").classList.remove("hidden");
        } catch (err) {
          console.error(err);
          swalError("Error", "No se pudo cargar el producto");
        }
      }

      function closeEditModal() {
        $("modal-edit")?.classList.add("hidden");
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
          iva_porcentaje: toFloat(fd.get("iva_porcentaje")) ?? 15,
        };

        try {
          const resProd = await swalLoading(
            fetch(`/productos/update/${id}`, {
              method: "PUT",
              headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": CSRF_TOKEN },
              body: JSON.stringify(productPayload),
            }),
            "Actualizando producto…"
          );

          if (!resProd.ok) {
            const err = await readJsonSafe(resProd);
            throw new Error(err?.message || "Error al actualizar producto");
          }

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

          if (!resPrice.ok) {
            const err = await readJsonSafe(resPrice);
            throw new Error(err?.message || "Error al guardar precios");
          }

          closeEditModal();
          await swalSuccess("Actualizado", "Producto actualizado");
          cargarProductos();
        } catch (err) {
          console.error(err);
          swalError("Error al actualizar", err.message || "Intenta nuevamente");
        }
      }

      async function cambiarEstadoProducto(id, nuevoEstado, confirmText) {
        const ok = await swalConfirm(confirmText);
        if (!ok) return;

        try {
          const res = await swalLoading(
            fetch(`/productos/${id}/estado`, {
              method: "PATCH",
              headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF_TOKEN,
              },
              body: JSON.stringify({ estado: !!nuevoEstado }),
            }),
            "Actualizando estado…"
          );

          if (!res.ok) {
            const err = await readJsonSafe(res);
            throw new Error(err?.message || "No se pudo actualizar el estado");
          }

          const data = await readJsonSafe(res);
          await swalSuccess("Listo", data?.message || "Estado actualizado");
          cargarProductos();
        } catch (err) {
          console.error(err);
          swalError("Error", err.message || "Intenta nuevamente");
        }
      }

      function exportarProductosExcel() {
        const estado = $("estado-select")?.value || "activos";
        const categoria = ($("categoria-select")?.value || "").trim();
        const q = ($("buscar-input")?.value || "").trim();

        const params = new URLSearchParams();
        params.set("estado", estado);
        if (categoria !== "") params.set("categoria", categoria);
        if (q !== "") params.set("q", q);

        const url = `{{ route('productos.export') }}?${params.toString()}`;
        window.location.href = url;
      }

      async function subirExcelProductos(file) {
        const formData = new FormData();
        formData.append("file", file);

        try {
          const res = await swalLoading(
            fetch("{{ route('productos.import') }}", {
              method: "POST",
              headers: { "X-CSRF-TOKEN": CSRF_TOKEN },
              body: formData,
            }),
            "Subiendo archivo y encolando importacion..."
          );

          const payload = await readJsonSafe(res);
          if (!res.ok) {
            throw new Error(payload?.message || "No se pudo iniciar la importacion");
          }

          const importId = payload?.data?.import_id;
          await swalSuccess("Importacion iniciada", `Proceso #${importId}`);

          if (importId) {
            await esperarImportacion(importId);
            cargarProductos();
          }
        } catch (err) {
          console.error(err);
          swalError("Error", err.message || "No se pudo procesar el archivo");
        }
      }

      async function esperarImportacion(importId) {
        const maxIntentos = 180;
        let intentos = 0;

        while (intentos < maxIntentos) {
          intentos++;
          await new Promise((resolve) => setTimeout(resolve, 2000));

          const res = await fetch(`/productos/import/${importId}/status`, {
            headers: { "Accept": "application/json" },
          });

          if (!res.ok) continue;
          const st = await res.json();

          if (st.status === "completed") {
            const resumen = [
              `Filas procesadas: ${st.processed_rows || 0}`,
              `Productos creados: ${st.created_count || 0}`,
              `Filas con error: ${st.failed_count || 0}`,
            ].join("\n");

            const errorPreview = st.error_log
              ? `\n\nErrores (resumen):\n${String(st.error_log).split("\n").slice(0, 10).join("\n")}`
              : "";

            await Swal.fire({
              icon: "success",
              title: "Importacion finalizada",
              text: resumen + errorPreview,
            });
            return;
          }

          if (st.status === "failed") {
            throw new Error(st.error_log || "La importacion fallo.");
          }
        }

        throw new Error("La importacion sigue en proceso. Revisa en unos segundos.");
      }

      window.openCreateModal = openCreateModal;
      window.closeCreateModal = closeCreateModal;

      window.aplicarFiltros = aplicarFiltros;
      window.cambiarPagina = cambiarPagina;

      window.openEditModal = openEditModal;
      window.closeEditModal = closeEditModal;

      window.cambiarEstadoProducto = cambiarEstadoProducto;
      window.exportarProductosExcel = exportarProductosExcel;
      window.subirExcelProductos = subirExcelProductos;
    })();
    </script>
</x-app-layout>
