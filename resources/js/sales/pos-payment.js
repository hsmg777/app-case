import { getCart, getTotals, clearCart } from "./pos-cart";
import { formatMoney, showSaleAlert, hideSaleAlert } from "./pos-utils";

function getIvaEnabled() {
  const el = document.getElementById("toggle_iva_global");
  return el ? !!el.checked : true;
}

function getCajaId() {
  const el = document.getElementById("caja_id");
  const raw = el?.value ? String(el.value).trim() : "";
  const n = parseInt(raw, 10);
  return Number.isFinite(n) && n > 0 ? n : null;
}

function buildCashierOpenUrl() {
  const base = (window.SALES_ROUTES?.cashierOpen) || "/cashier/open";
  const cajaId = getCajaId();
  const bodegaId = document.getElementById("bodega_id")?.value || null;

  const url = new URL(base, window.location.origin);
  url.searchParams.set("return_to", window.location.href);
  if (bodegaId) url.searchParams.set("bodega_id", bodegaId);
  if (cajaId) url.searchParams.set("caja_id", String(cajaId));
  return url.toString();
}

function ensureCajaOrRedirect() {
  const cajaId = getCajaId();
  if (!cajaId) {
    window.location.href = buildCashierOpenUrl();
    return false;
  }
  return true;
}


// function openPaymentModal removed
// function buildCashierOpenUrl kept helper

// function closePaymentModal removed

function recalcCambio() {
  const totals = getTotals();
  const recibido = parseFloat(
    document.getElementById("payment_modal_monto_recibido")?.value || "0"
  );

  const total = Number(totals.total || 0);
  const cambio = recibido - total;

  const span = document.getElementById("payment_modal_cambio");
  if (span) span.textContent = formatMoney(cambio > 0 ? cambio : 0);
}

function showChangeModal(total, recibido, cambio) {
  const totalEl = document.getElementById("change_total");
  const recEl = document.getElementById("change_recibido");
  const camEl = document.getElementById("change_cambio");

  if (totalEl) totalEl.textContent = formatMoney(total);
  if (recEl) recEl.textContent = formatMoney(recibido);
  if (camEl) camEl.textContent = formatMoney(cambio > 0 ? cambio : 0);

  const modal = document.getElementById("change-modal");
  if (modal) modal.classList.remove("hidden");
}

function closeChangeModal() {
  const modal = document.getElementById("change-modal");
  if (modal) modal.classList.add("hidden");
}

let submitting = false;

async function submitSaleFromModal() {
  if (!ensureCajaOrRedirect()) return;
  if (submitting) return;
  submitting = true;

  const btnConfirm = document.getElementById("btn-confirm-payment");
  if (btnConfirm) btnConfirm.disabled = true;

  try {
    const cart = getCart();
    if (!cart || cart.length === 0) {
      showSaleAlert("Debes agregar al menos un producto al carrito.", true);
      return;
    }

    // Totales SOLO para UI/validación de recibido (backend recalcula y guarda)
    const totals = getTotals();
    const totalUi = Number(totals.total || 0);

    const ivaEnabled = getIvaEnabled();

    const bodegaId = document.getElementById("bodega_id")?.value;
    const fechaVenta = document.getElementById("fecha_venta")?.value;
    const tipoDocumento =
      document.getElementById("tipo_documento")?.value || "FACTURA";
    const numFactura = document.getElementById("num_factura")?.value || null;

    // Nota: sale_observaciones fue removido, usamos payment.observaciones como general
    const observacionesVenta =
      document.getElementById("payment_modal_observaciones")?.value || null;

    if (!bodegaId || !fechaVenta) {
      showSaleAlert("Completa los datos de la venta.", true);
      return;
    }

    const recibido = parseFloat(
      document.getElementById("payment_modal_monto_recibido")?.value || "0"
    );

    // Validación: recibido vs total UI
    if (recibido < totalUi) {
      showSaleAlert("El monto recibido no puede ser menor al total. Total: " + totalUi, true);
      submitting = false; // Reset submitting
      if (btnConfirm) btnConfirm.disabled = false;
      return;
    }

    const metodoSelect = document.getElementById("payment_modal_metodo");
    const metodo = metodoSelect?.value;
    const paymentMethodId = metodoSelect?.selectedOptions[0]?.dataset.id || null;

    const referencia =
      document.getElementById("payment_modal_referencia")?.value || null;

    const observacionesPago =
      document.getElementById("payment_modal_observaciones")?.value || null;

    const clientId = document.getElementById("client_id")?.value || null;

    const emailSelect = document.getElementById("cliente_email");
    const selectedOpt = emailSelect?.selectedOptions?.[0];

    const clientEmailId = emailSelect && emailSelect.value ? emailSelect.value : null;
    const emailDestino = selectedOpt && selectedOpt.value ? selectedOpt.text : null;

    // VALIDACIÓN CLIENTE: Si es >= 50, no puede ser consumidor final
    // Buscamos info del cliente en el DOM (seteada por pos-client.js)
    const clientInputEl = document.getElementById("client_id");

    // Obtenemos los data-atributes para saber "quién" es el cliente actual segun el UI
    // Si no hay client_id, el script pos-client asume CF, pero acá validamos explícitamente.
    // Ojo: cuando seleccionas un cliente real, el value se llena. Si es CF el value podría estar vacío o ser el ID del CF.
    // Usaremos la referencia del nombre/ident en el UI para estar seguros.
    const clientNameUI = document.getElementById("cliente_nombre")?.textContent?.trim()?.toUpperCase() || "";
    const clientIdentUI = document.getElementById("cliente_identificacion")?.textContent?.trim() || "";

    // Lógica para detectar CF en frontend
    const isCF =
      !clientId || // Si no tiene ID seleccionado es el default
      clientIdentUI === '9999999999999' ||
      clientNameUI === 'CONSUMIDOR FINAL';

    if (totalUi >= 50 && isCF) {
      showSaleAlert("Para ventas de $50 o más, es OBLIGATORIO ingresar un cliente con datos (no Consumidor Final).", true);
      submitting = false;
      if (btnConfirm) btnConfirm.disabled = false;
      return;
    }


    const cajaId = getCajaId();

    const payload = {
      caja_id: cajaId,
      client_email_id: clientEmailId ? Number(clientEmailId) : null,
      email_destino: emailDestino,
      client_id: clientId || null,
      user_id: window.AUTH_USER_ID || null,
      bodega_id: bodegaId,
      fecha_venta: fechaVenta,
      tipo_documento: tipoDocumento,
      num_factura: numFactura,
      observaciones: observacionesVenta,

      iva_enabled: ivaEnabled,

      items: cart.map((item) => {
        const qty = Number(item.cantidad) || 1;

        const lineSubtotal =
          Number(item.lineSubtotal ?? 0) ||
          (Number(item.total ?? 0) + Number(item.descuento ?? 0));

        const precioEfectivo = qty > 0
          ? (lineSubtotal / qty)
          : Number(item.precio_unitario ?? 0);

        return {
          producto_id: item.producto_id,
          descripcion: item.descripcion,
          cantidad: qty,
          precio_unitario: Number(precioEfectivo || 0).toFixed(2),
          descuento: Number(item.descuento || 0),
          iva_porcentaje: item.iva_porcentaje ?? 15,
          percha_id: item.percha_id ?? null,
        };
      }),


      payment: {
        metodo,
        payment_method_id: paymentMethodId,
        monto_recibido: Number(recibido || 0).toFixed(2),
        referencia,
        observaciones: observacionesPago,
        fecha_pago: fechaVenta,
      },

      email_destino: emailDestino,
    };

    const routes = window.SALES_ROUTES || {};
    let url = routes.store || "/api/ventas";

    if (!url) {
      showSaleAlert(
        "Ruta de venta no configurada (ni siquiera fallback).",
        true
      );
      return;
    }

    const csrfToken =
      window.CSRF_TOKEN ||
      document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
      "";

    const res = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-TOKEN": csrfToken,
      },
      body: JSON.stringify(payload),
    });

    if (res.status === 422) {
      const data = await res.json();

      const msg = (data?.message || "").toLowerCase();
      const hasCajaError =
        msg.includes("no hay caja abierta") ||
        msg.includes("caja") ||
        !!data?.errors?.caja_id;

      if (hasCajaError) {
        window.location.href = buildCashierOpenUrl();
        return;
      }

      showSaleAlert(data?.message || "Error de validación en la venta.", true);
      return;
    }


    if (!res.ok) {
      showSaleAlert("Ocurrió un error al registrar la venta.", true);
      return;
    }

    const data = await res.json();

    showSaleAlert(data.message || "Venta registrada correctamente.");

    // OJO: el backend devuelve el total real guardado (incluye IVA exacto)
    const sale = data?.data;
    const saleId = sale?.id;

    // Total REAL desde backend (no del UI)
    const totalReal = Number(sale?.total ?? totalUi);
    const cambioReal = recibido - totalReal;

    if (saleId) {
      const frame = document.getElementById("ticketPrintFrame");
      if (frame) {
        frame.src = `/ventas/${saleId}/ticket?autoprint=1&embed=1&ts=${Date.now()}`;
      }
    }

    // closePaymentModal() removed
    showChangeModal(totalReal, recibido, cambioReal);

    // Limpiar carrito y formularios
    clearCart();

    // sale_observaciones removed
    const refPago = document.getElementById("payment_modal_referencia");
    if (refPago) refPago.value = "";

    const obsPago = document.getElementById("payment_modal_observaciones");
    if (obsPago) obsPago.value = "";
  } catch (e) {
    console.error(e);
    showSaleAlert("Error de comunicación con el servidor.", true);
  } finally {
    submitting = false;
    if (btnConfirm) btnConfirm.disabled = false;
  }
}

export function initPayment() {
  // btn-open-payment-modal listener removed, btn-confirm-payment is now the trigger

  // payment-modal close listeners removed

  const changeModal = document.getElementById("change-modal");
  if (changeModal) {
    changeModal.querySelectorAll("[data-change-close]").forEach((btn) => {
      btn.addEventListener("click", closeChangeModal);
    });
  }

  const inputRecibido = document.getElementById("payment_modal_monto_recibido");
  if (inputRecibido) inputRecibido.addEventListener("input", recalcCambio);

  const btnConfirm = document.getElementById("btn-confirm-payment");
  if (btnConfirm) btnConfirm.addEventListener("click", submitSaleFromModal);
}
