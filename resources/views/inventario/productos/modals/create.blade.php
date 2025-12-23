<div id="modal-create" class="hidden fixed inset-0 z-50 bg-black/40 items-center justify-center">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl">
    <!-- Header -->
    <div class="px-6 py-4 border-b flex items-center justify-between">
      <h2 class="text-xl font-semibold text-blue-900">Nuevo Producto</h2>
      <button type="button" data-create-close class="text-gray-500 hover:text-gray-700">✕</button>
    </div>

    <form id="form-create-product" class="p-6">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- COL IZQUIERDA: DETALLES -->
        <section class="bg-blue-50/40 border border-blue-100 rounded-lg p-4">
          <h3 class="text-sm font-semibold text-blue-900 mb-3">Detalles del producto</h3>

          <div class="space-y-3">
            <div>
              <label class="text-xs text-blue-800">Nombre *</label>
              <input name="nombre" class="w-full border rounded-md px-3 py-2" placeholder="Ej. Lápiz HB amarillo" required>
              <p class="text-[11px] text-slate-500 mt-1">Nombre que verán en ventas e inventario.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs text-blue-800">Categoría</label>
                <input name="categoria" class="w-full border rounded-md px-3 py-2" placeholder="Útiles escolares">
              </div>
              <div>
                <label class="text-xs text-blue-800">Unidad de medida *</label>
                <input name="unidad_medida" value="unidad" class="w-full border rounded-md px-3 py-2" placeholder="unidad / caja / paquete" required>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs text-blue-800">Código de barras</label>
                <input name="codigo_barras" class="w-full border rounded-md px-3 py-2" placeholder="EAN/UPC (opcional)">
              </div>
              <div>
                <label class="text-xs text-blue-800">Código interno</label>
                <input name="codigo_interno" class="w-full border rounded-md px-3 py-2" placeholder="SKU propio (opcional)">
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs text-blue-800">Stock mínimo *</label>
                <input type="number" name="stock_minimo" value="0" min="0" class="w-full border rounded-md px-3 py-2" placeholder="Ej. 20" required>
                <p class="text-[11px] text-slate-500 mt-1">Te avisaremos cuando el stock baje de este valor.</p>
              </div>

              <div>
                <label class="text-xs text-blue-800">IVA del producto (%) *</label>
                <select name="iva_porcentaje" class="w-full border rounded-md px-3 py-2" required>
                  <option value="15" selected>15% (Grava IVA)</option>
                  <option value="0">0% (IVA 0)</option>
                </select>
                <p class="text-[11px] text-slate-500 mt-1">Este % viajará al carrito para calcular el IVA por item.</p>
              </div>
            </div>

            <div>
              <label class="text-xs text-blue-800">Descripción</label>
              <textarea name="descripcion" class="w-full border rounded-md px-3 py-2" rows="3" placeholder="Notas o características (opcional)"></textarea>
            </div>
          </div>
        </section>

        <!-- COL DERECHA: PRECIOS -->
        <section class="bg-slate-50 border border-slate-200 rounded-lg p-4">
          <h3 class="text-sm font-semibold text-slate-800 mb-3">Reglas de precio</h3>

          <!-- Precio base -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs text-slate-700">Precio unitario *</label>
              <input type="number" step="0.01" min="0" name="precio_unitario" class="w-full border rounded-md px-3 py-2" placeholder="Ej. 1.50" required>
            </div>
            <div>
              <label class="text-xs text-slate-700">Moneda</label>
              <div class="w-full border rounded-md px-3 py-2 bg-gray-100 text-gray-700 text-sm">
                USD
              </div>
              <input type="hidden" name="moneda" value="USD">
            </div>
          </div>

          <!-- Descuento por cantidad -->
          <div class="mt-5">
            <label class="inline-flex items-center gap-2 cursor-pointer">
              <input id="create-toggle-descuento" type="checkbox" class="rounded border-gray-300">
              <span class="text-sm text-slate-800 font-medium">Activar descuento por cantidad</span>
            </label>
            <p class="text-[11px] text-slate-500 mt-1">Aplica un precio especial cuando compren dentro de un rango de unidades.</p>

            <div id="create-box-descuento" class="mt-3 grid grid-cols-3 gap-3 opacity-50 pointer-events-none">
              <div>
                <label class="text-xs text-slate-700">Cant. mín.</label>
                <input type="number" min="1" name="cantidad_min" class="w-full border rounded-md px-3 py-2" placeholder="Ej. 6" disabled>
              </div>
              <div>
                <label class="text-xs text-slate-700">Cant. máx.</label>
                <input type="number" min="1" name="cantidad_max" class="w-full border rounded-md px-3 py-2" placeholder="Ej. 11" disabled>
              </div>
              <div>
                <label class="text-xs text-slate-700">Precio por cantidad</label>
                <input type="number" step="0.01" min="0" name="precio_por_cantidad" class="w-full border rounded-md px-3 py-2" placeholder="Ej. 1.25" disabled>
              </div>
            </div>
          </div>

          <!-- Venta por caja -->
          <div class="mt-5">
            <label class="inline-flex items-center gap-2 cursor-pointer">
              <input id="create-toggle-caja" type="checkbox" class="rounded border-gray-300">
              <span class="text-sm text-slate-800 font-medium">Activar venta por caja</span>
            </label>
            <p class="text-[11px] text-slate-500 mt-1">Define cuántas unidades trae la caja y su precio total.</p>

            <div id="create-box-caja" class="mt-3 grid grid-cols-2 gap-3 opacity-50 pointer-events-none">
              <div>
                <label class="text-xs text-slate-700">Unidades por caja</label>
                <input type="number" min="1" name="unidades_por_caja" class="w-full border rounded-md px-3 py-2" placeholder="Ej. 12" disabled>
              </div>
              <div>
                <label class="text-xs text-slate-700">Precio por caja</label>
                <input type="number" step="0.01" min="0" name="precio_por_caja" class="w-full border rounded-md px-3 py-2" placeholder="Ej. 13.80" disabled>
              </div>
            </div>
          </div>

          <ul class="mt-4 text-[11px] text-slate-500 space-y-1 list-disc list-inside">
            <li>Si no activas un bloque, esos campos no se guardan.</li>
            <li>El precio por caja se usa primero (cantidad divisible). Lo restante aplica precio por cantidad o unitario.</li>
          </ul>
        </section>
      </div>

      <!-- Footer -->
      <div class="flex justify-end gap-3 mt-6">
        <button type="button" data-create-close class="px-4 py-2 rounded border border-gray-300 text-gray-700">Cancelar</button>
        <button type="submit" class="px-4 py-2 rounded bg-blue-700 text-white hover:bg-blue-800">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function initCreateToggles() {
    if (window.__createProductTogglesReady) return;
    window.__createProductTogglesReady = true;

    const tDesc = document.getElementById('create-toggle-descuento');
    const boxDesc = document.getElementById('create-box-descuento');
    const tCaja = document.getElementById('create-toggle-caja');
    const boxCaja = document.getElementById('create-box-caja');

    const setBlock = (toggle, box) => {
      const inputs = box.querySelectorAll('input');
      if (toggle.checked) {
        box.classList.remove('opacity-50', 'pointer-events-none');
        inputs.forEach(i => (i.disabled = false));
      } else {
        box.classList.add('opacity-50', 'pointer-events-none');
        inputs.forEach(i => { i.value = ""; i.disabled = true; });
      }
    };

    if (tDesc && boxDesc) {
      tDesc.addEventListener('change', () => setBlock(tDesc, boxDesc));
      setBlock(tDesc, boxDesc);
    }
    if (tCaja && boxCaja) {
      tCaja.addEventListener('change', () => setBlock(tCaja, boxCaja));
      setBlock(tCaja, boxCaja);
    }
  })();
</script>
