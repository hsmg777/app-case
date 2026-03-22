<div id="modal-bulk-prices" class="hidden fixed inset-0 z-50 bg-black/40 items-center justify-center">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl">
    <div class="px-6 py-4 border-b flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-blue-900">Actualizar precios en lote</h2>
        <p class="text-sm text-slate-500 mt-1">
          Aplicarás las mismas reglas de precio a
          <span id="bulk-price-selected-count" class="font-semibold text-slate-800">0</span>
          productos seleccionados.
        </p>
      </div>
      <button type="button" data-bulk-price-close class="text-gray-500 hover:text-gray-700">✕</button>
    </div>

    <form id="form-bulk-prices" class="p-6">
      <section class="bg-slate-50 border border-slate-200 rounded-lg p-4">
        <h3 class="text-sm font-semibold text-slate-800 mb-3">Reglas de precio</h3>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs text-slate-700">Precio unitario *</label>
            <input
              type="number"
              step="0.01"
              min="0"
              name="precio_unitario"
              class="w-full border rounded-md px-3 py-2"
              placeholder="Ej. 1.50"
              required
            >
          </div>
          <div>
            <label class="text-xs text-slate-700">Moneda</label>
            <div class="w-full border rounded-md px-3 py-2 bg-gray-100 text-gray-700 text-sm">USD</div>
            <input type="hidden" name="moneda" value="USD">
          </div>
        </div>

        <div class="mt-5">
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <input id="bulk-toggle-descuento" type="checkbox" class="rounded border-gray-300">
            <span class="text-sm text-slate-800 font-medium">Activar descuento por cantidad</span>
          </label>
          <p class="text-[11px] text-slate-500 mt-1">Estas reglas se copiarán igual a todos los productos seleccionados.</p>

          <div id="bulk-box-descuento" class="mt-3 grid grid-cols-3 gap-3 opacity-50 pointer-events-none">
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

        <div class="mt-5">
          <label class="inline-flex items-center gap-2 cursor-pointer">
            <input id="bulk-toggle-caja" type="checkbox" class="rounded border-gray-300">
            <span class="text-sm text-slate-800 font-medium">Activar venta por caja</span>
          </label>
          <p class="text-[11px] text-slate-500 mt-1">Se reemplazarán las reglas de caja actuales en todos los productos seleccionados.</p>

          <div id="bulk-box-caja" class="mt-3 grid grid-cols-2 gap-3 opacity-50 pointer-events-none">
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
      </section>

      <div class="flex justify-end gap-3 mt-6">
        <button type="button" data-bulk-price-close class="px-4 py-2 rounded border border-gray-300 text-gray-700">
          Cancelar
        </button>
        <button type="submit" class="px-4 py-2 rounded bg-blue-700 text-white hover:bg-blue-800">
          Guardar en seleccionados
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  (function initBulkPriceToggles() {
    if (window.__bulkProductPriceTogglesReady) return;
    window.__bulkProductPriceTogglesReady = true;

    const tDesc = document.getElementById('bulk-toggle-descuento');
    const boxDesc = document.getElementById('bulk-box-descuento');
    const tCaja = document.getElementById('bulk-toggle-caja');
    const boxCaja = document.getElementById('bulk-box-caja');

    const setBlock = (toggle, box) => {
      const inputs = box.querySelectorAll('input');
      if (toggle.checked) {
        box.classList.remove('opacity-50', 'pointer-events-none');
        inputs.forEach((input) => {
          input.disabled = false;
        });
        return;
      }

      box.classList.add('opacity-50', 'pointer-events-none');
      inputs.forEach((input) => {
        input.value = "";
        input.disabled = true;
      });
    };

    window.resetBulkPriceFormState = function resetBulkPriceFormState() {
      const form = document.getElementById('form-bulk-prices');
      form?.reset();

      if (tDesc && boxDesc) {
        tDesc.checked = false;
        setBlock(tDesc, boxDesc);
      }

      if (tCaja && boxCaja) {
        tCaja.checked = false;
        setBlock(tCaja, boxCaja);
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
