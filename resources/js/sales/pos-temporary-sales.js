import { clearCart, getCart, getCartSnapshot, getTotals, replaceCart } from './pos-cart';
import {
  clearSelectedClient,
  getSelectedClientSnapshot,
  restoreClientSelection,
} from './pos-client';
import { formatMoney, showSaleAlert } from './pos-utils';

const STORAGE_PREFIX = 'pos_temp_sales_v1';
const MAX_TEMP_SALES = 3;

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function getCajaId() {
  const raw = document.getElementById('caja_id')?.value?.trim() || 'sin-caja';
  return raw || 'sin-caja';
}

function getBodegaId() {
  const raw = document.getElementById('bodega_id')?.value?.trim() || 'sin-bodega';
  return raw || 'sin-bodega';
}

function getStorageKey() {
  return `${STORAGE_PREFIX}:${getCajaId()}:${getBodegaId()}`;
}

function readTempSales() {
  try {
    const raw = window.localStorage.getItem(getStorageKey());
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch (error) {
    console.error('[POS] No se pudieron leer las facturas temporales:', error);
    return [];
  }
}

function writeTempSales(items) {
  try {
    window.localStorage.setItem(
      getStorageKey(),
      JSON.stringify(Array.isArray(items) ? items.slice(0, MAX_TEMP_SALES) : [])
    );
  } catch (error) {
    console.error('[POS] No se pudieron guardar las facturas temporales:', error);
    throw error;
  }
}

function getTempSalesListElements() {
  return {
    list: document.getElementById('temp-sales-list'),
    empty: document.getElementById('temp-sales-empty'),
  };
}

function formatSavedAt(value) {
  if (!value) return 'Sin fecha';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Sin fecha';

  return new Intl.DateTimeFormat('es-EC', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
}

function getItemsMeta(items = []) {
  const lineas = Array.isArray(items) ? items.length : 0;
  const unidades = Array.isArray(items)
    ? items.reduce((acc, item) => acc + (parseInt(item?.cantidad, 10) || 0), 0)
    : 0;
  const firstItem = Array.isArray(items) && items.length ? items[0] : null;
  const firstDescription = String(firstItem?.descripcion || '').trim();

  return {
    lineas,
    unidades,
    preview:
      lineas <= 1
        ? firstDescription || 'Sin detalle'
        : `${firstDescription || 'Varios productos'} +${lineas - 1}`,
  };
}

function buildTempSaleSnapshot() {
  const cartSnapshot = getCartSnapshot();
  const clientSnapshot = getSelectedClientSnapshot();
  const totals = getTotals();
  const itemsMeta = getItemsMeta(cartSnapshot);

  return {
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    createdAt: new Date().toISOString(),
    cajaId: getCajaId(),
    bodegaId: getBodegaId(),
    total: Number(totals.total || 0),
    items: cartSnapshot,
    client: clientSnapshot,
    meta: itemsMeta,
  };
}

function resetTemporaryDraftInputs() {
  const referenceInput = document.getElementById('payment_modal_referencia');
  const observationsInput = document.getElementById('payment_modal_observaciones');
  const quickSearch = document.getElementById('client_quick_search');

  if (referenceInput) referenceInput.value = '';
  if (observationsInput) observationsInput.value = '';
  if (quickSearch) quickSearch.value = '';
}

function renderTempSales() {
  const { list, empty } = getTempSalesListElements();
  if (!list || !empty) return;

  const tempSales = readTempSales();
  list.innerHTML = '';

  if (!tempSales.length) {
    empty.classList.remove('hidden');
    return;
  }

  empty.classList.add('hidden');

  tempSales.forEach((sale, index) => {
    const clientName = sale?.client?.name || 'Consumidor final';
    const ident = sale?.client?.ident || 'Sin identificacion';
    const meta = sale?.meta || getItemsMeta(sale?.items || []);
    const card = document.createElement('article');
    card.className =
      'bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden';

    card.innerHTML = `
      <button
        type="button"
        data-temp-sale-restore
        data-temp-sale-id="${escapeHtml(sale.id)}"
        class="w-full text-left px-3 py-3 hover:bg-slate-50 transition"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
              Temporal ${index + 1}
            </p>
            <p class="text-sm font-semibold text-slate-800 truncate">
              ${escapeHtml(clientName)}
            </p>
            <p class="text-[11px] text-slate-500 truncate">
              ${escapeHtml(ident)}
            </p>
          </div>
          <div class="text-right shrink-0">
            <p class="text-sm font-bold text-blue-700">${escapeHtml(formatMoney(sale.total || 0))}</p>
            <p class="text-[11px] text-slate-400">${escapeHtml(formatSavedAt(sale.createdAt))}</p>
          </div>
        </div>

        <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-slate-500">
          <span>${escapeHtml(`${meta.lineas || 0} linea(s) · ${meta.unidades || 0} und`)}</span>
          <span class="truncate text-right">${escapeHtml(meta.preview || 'Sin detalle')}</span>
        </div>
      </button>

      <div class="px-3 pb-3 flex justify-end">
        <button
          type="button"
          data-temp-sale-delete
          data-temp-sale-id="${escapeHtml(sale.id)}"
          class="text-[11px] font-semibold text-rose-600 hover:text-rose-700"
        >
          Eliminar
        </button>
      </div>
    `;

    list.appendChild(card);
  });
}

function persistTempSale(snapshot) {
  const tempSales = readTempSales();
  tempSales.unshift(snapshot);
  writeTempSales(tempSales);
  renderTempSales();
}

function removeTempSale(id, showMessage = false) {
  const next = readTempSales().filter((sale) => sale?.id !== id);
  writeTempSales(next);
  renderTempSales();

  if (showMessage) {
    showSaleAlert('Factura temporal eliminada.');
  }
}

function currentDraftNeedsConfirmation() {
  if (getCart().length > 0) return true;

  const currentClient = getSelectedClientSnapshot();
  return !!(currentClient?.clientId && !currentClient?.isConsumidorFinal);
}

async function confirmRestoreIfNeeded() {
  if (!currentDraftNeedsConfirmation()) return true;

  if (window.Swal) {
    const result = await window.Swal.fire({
      title: 'Reemplazar venta actual',
      text: 'La venta que tienes en pantalla se reemplazará por la factura temporal seleccionada.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, recuperar',
      cancelButtonText: 'Cancelar',
    });

    return !!result.isConfirmed;
  }

  return window.confirm(
    'La venta que tienes en pantalla se reemplazará por la factura temporal seleccionada. Deseas continuar?'
  );
}

async function restoreTempSale(id) {
  const tempSales = readTempSales();
  const selected = tempSales.find((sale) => sale?.id === id);
  if (!selected) {
    showSaleAlert('No se encontró la factura temporal seleccionada.', true);
    renderTempSales();
    return;
  }

  const confirmed = await confirmRestoreIfNeeded();
  if (!confirmed) return;

  replaceCart(selected.items || []);
  await restoreClientSelection(selected.client || null);
  resetTemporaryDraftInputs();

  removeTempSale(id, false);
  showSaleAlert('Factura temporal recuperada correctamente.');
}

function saveCurrentSaleTemporarily() {
  if (!getCart().length) {
    showSaleAlert('Debes agregar productos al carrito antes de guardarla temporalmente.', true);
    return;
  }

  const tempSales = readTempSales();
  if (tempSales.length >= MAX_TEMP_SALES) {
    showSaleAlert('Ya tienes 3 facturas temporales guardadas para esta caja y bodega.', true);
    return;
  }

  try {
    const snapshot = buildTempSaleSnapshot();
    persistTempSale(snapshot);

    clearCart();
    clearSelectedClient();
    resetTemporaryDraftInputs();

    showSaleAlert('Factura guardada temporalmente.');
  } catch (error) {
    console.error('[POS] Error guardando factura temporal:', error);
    showSaleAlert('No se pudo guardar la factura temporalmente.', true);
  }
}

export function initTemporarySales() {
  const saveButton = document.getElementById('btn-save-temp-sale');
  const { list } = getTempSalesListElements();

  if (!saveButton || !list) return;

  saveButton.addEventListener('click', saveCurrentSaleTemporarily);

  list.addEventListener('click', async (event) => {
    const deleteButton = event.target.closest('[data-temp-sale-delete]');
    if (deleteButton) {
      removeTempSale(deleteButton.dataset.tempSaleId, true);
      return;
    }

    const restoreButton = event.target.closest('[data-temp-sale-restore]');
    if (restoreButton) {
      await restoreTempSale(restoreButton.dataset.tempSaleId);
    }
  });

  renderTempSales();
}
