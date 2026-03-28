// resources/js/sales/pos-client.js
import { showSaleAlert } from './pos-utils';

console.log('[POS] pos-client.js cargado');
let createClientFormInitialized = false;

function getCfMeta() {
  const el = document.getElementById('client_id');
  return {
    name:  el?.dataset?.cfName  || 'CONSUMIDOR FINAL',
    ident: el?.dataset?.cfIdent || '9999999999999',
  };
}

function setConsumidorFinalUI() {
  const { name, ident } = getCfMeta();

  const inputId = document.getElementById('client_id');
  const inputName = document.getElementById('cliente_nombre');
  const identEl = document.getElementById('cliente_identificacion');

  if (inputId) {
    inputId.value = '';
    inputId.dispatchEvent(new Event('change', { bubbles: true }));
  }
  if (inputName) inputName.textContent = name;
  if (identEl) identEl.textContent = ident;

  const select = document.getElementById('cliente_email');
  const resumen = document.getElementById('cliente_email_resumen');
  if (select) select.innerHTML = '<option value="">Sin correo (Consumidor Final)</option>';
  if (resumen) resumen.textContent = 'Sin correo seleccionado';
}


function debounce(fn, delay = 300) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
    };
}

// Cache con todos los clientes para el POS
let allClients = [];
let allClientsLoaded = false;

/**
 * Carga TODOS los clientes una sola vez desde /clients?per_page=500&all=1
 */
async function loadAllClients() {
    if (allClientsLoaded && allClients.length) {
        return allClients;
    }

    let indexUrl = '/clients'; // fallback por defecto

    if (window.SALES_ROUTES) {
        if (window.SALES_ROUTES.clientIndex) {
            indexUrl = window.SALES_ROUTES.clientIndex;
        } else {
            console.warn(
                '[POS] clientIndex no viene en SALES_ROUTES, usando /clients por defecto',
                window.SALES_ROUTES
            );
        }
    } else {
        console.warn('[POS] window.SALES_ROUTES no existe, usando /clients por defecto');
    }

    try {
        const url = `${indexUrl}?per_page=500&all=1`;
        console.log('[POS] Cargando clientes desde:', url);

        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        console.log('[POS] status /clients:', res.status);

        if (!res.ok) {
            console.error('[POS] Error al cargar lista de clientes para POS');
            allClientsLoaded = true;
            return allClients;
        }

        const json = await res.json();

        // Soportar tanto un arreglo simple como un paginador { data: [...] }
        let data = [];
        if (Array.isArray(json)) {
            data = json;
        } else if (Array.isArray(json.data)) {
            data = json.data;
        }

        allClients = data;
        allClientsLoaded = true;

        console.log('[POS] Clientes cargados para POS:', allClients.length);
        return allClients;
    } catch (e) {
        console.error('[POS] Excepción cargando clientes:', e);
        allClientsLoaded = true;
        return allClients;
    }
}

/**
 * Selecciona automáticamente el cliente "Consumidor Final"
 * si aún no hay client_id asignado en el POS.
 */
async function selectDefaultConsumidorFinalIfEmpty() {
  const clientIdInput = document.getElementById('client_id');
  if (!clientIdInput || clientIdInput.value) return;
  setConsumidorFinalUI();
}




/**
 * Pinta las filas en la tabla del modal de clientes.
 */
function renderClientResults(clients) {
    const tbody = document.getElementById('client_results');
    const empty = document.getElementById('client_results_empty');
    if (!tbody || !empty) {
        console.warn('[POS] No se encontró tbody o empty para resultados de clientes');
        return;
    }

    tbody.innerHTML = '';

    if (!clients.length) {
        empty.textContent = 'No se encontraron clientes.';
        empty.classList.remove('hidden');
        return;
    }

    empty.classList.add('hidden');

    clients.forEach((c) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50 cursor-pointer';
        tr.dataset.clientId = c.id;
        tr.dataset.clientName = c.business || c.nombre || '';
        tr.dataset.clientIdentificacion = c.identificacion || '';

        tr.innerHTML = `
            <td class="px-3 py-2">
                <div class="font-semibold text-gray-800 text-xs">
                    ${c.business || c.nombre || 'Sin nombre'}
                </div>
                <div class="text-[11px] text-gray-400">
                    ${(c.tipo || '').toString().charAt(0).toUpperCase() + (c.tipo || '').toString().slice(1)} · ${c.ciudad || ''}
                </div>
            </td>
            <td class="px-3 py-2 text-xs text-gray-700">
                ${c.identificacion || '-'}
            </td>
            <td class="px-3 py-2 text-xs text-gray-700">
                ${c.tipo_identificacion || '-'}
            </td>
            <td class="px-3 py-2 text-center text-xs text-blue-600">
                Seleccionar
            </td>
        `;
        tbody.appendChild(tr);
    });
}

/**
 * Filtra la lista cacheada de clientes por el término ingresado
 * y llama a renderClientResults().
 */
function filterAndRenderClients(term) {
    renderClientResults(getFilteredClients(term));
}

/**
 * Carga los emails del cliente para el select de correo.
 */
async function loadClientEmails(clientId, preferredEmailId = null) {
  const select  = document.getElementById('cliente_email');
  const resumen = document.getElementById('cliente_email_resumen');

  console.log('[POS] loadClientEmails() llamado con clientId =', clientId);
  console.log('[POS] select encontrado?', !!select);

    if (!select) return;

    if (!clientId) {
    select.innerHTML = '<option value="">Sin correo (Consumidor Final)</option>';
    if (resumen) resumen.textContent = 'Sin correo seleccionado';
    return;
    }


  select.innerHTML = '<option value="">Cargando correos...</option>';
  if (resumen) resumen.textContent = 'Cargando correos...';

  try {
    const base = window.SALES_ROUTES?.clientEmailsBase || '/clients';
    const url = `${base}/${clientId}/emails`;

    console.log('[POS] Fetch a:', url);

    const res = await fetch(url, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });

    console.log('[POS] Status correos:', res.status);

    if (!res.ok) {
      select.innerHTML = '<option value="">Sin correos disponibles</option>';
      if (resumen) resumen.textContent = 'Sin correo seleccionado';
      return;
    }

    const raw = await res.json();
    console.log('[POS] Respuesta correos cruda:', raw);

    let emails = [];

    if (Array.isArray(raw)) {
      if (raw.length && typeof raw[0] === 'object') {
        emails = raw
          .map(e => ({
            id: e?.id ?? e?.client_email_id ?? null,
            email: e?.email ?? e?.correo ?? e?.mail ?? null,
          }))
          .filter(x => x.id && x.email);
      }

      else if (raw.length && typeof raw[0] === 'string') {
        emails = raw
          .filter(Boolean)
          .map((email, idx) => ({ id: `str_${idx}`, email }));
      }
    } else if (raw && Array.isArray(raw.data)) {
      emails = raw.data
        .map(e => ({
          id: e?.id ?? e?.client_email_id ?? null,
          email: e?.email ?? e?.correo ?? e?.mail ?? null,
        }))
        .filter(x => x.id && x.email);
    }

    console.log('[POS] Emails procesados:', emails);
    window.__POS_LAST_EMAILS__ = { clientId, raw, emails };

    while (select.options.length) select.remove(0);

    select.add(new Option('Selecciona un correo (opcional)', ''));

    if (!emails.length) {
      if (resumen) resumen.textContent = 'Sin correo seleccionado';
      return;
    }

    let first = null;
    let preferred = null;

    emails.forEach(({ id, email }) => {
      const opt = new Option(email, String(id));
      opt.dataset.email = email; 
      select.add(opt);
      if (!first) first = { id, email };
      if (preferredEmailId != null && String(id) === String(preferredEmailId)) {
        preferred = { id, email };
      }
    });

    const selected = preferred || first;

    if (selected) {
      select.value = String(selected.id);
      if (resumen) resumen.textContent = `Enviar factura a: ${selected.email}`;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    } else {
      if (resumen) resumen.textContent = 'Sin correo seleccionado';
    }
  } catch (e) {
    console.error('[POS] Error al cargar correos de cliente:', e);
    while (select.options.length) select.remove(0);
    select.add(new Option('Error al cargar correos', ''));
    if (resumen) resumen.textContent = 'Error al cargar correos';
  }
}

function isConsumidorFinalByIdent(ident) {
  const { ident: cfIdent } = getCfMeta();
  const clean = String(ident || '').replace(/\D+/g, '');
  const cfClean = String(cfIdent || '').replace(/\D+/g, '');
  return clean && cfClean && clean === cfClean;
}

function normalizeClientTerm(value) {
  return String(value || '').trim().toLowerCase();
}

function getFilteredClients(term) {
  const normalized = normalizeClientTerm(term);

  if (!normalized) {
    return allClients;
  }

  return allClients.filter((c) => {
    const name = normalizeClientTerm(c.business || c.nombre || '');
    const identificacion = normalizeClientTerm(c.identificacion || '');
    const telefono = normalizeClientTerm(c.telefono || '');
    const ciudad = normalizeClientTerm(c.ciudad || '');

    return (
      name.includes(normalized) ||
      identificacion.includes(normalized) ||
      telefono.includes(normalized) ||
      ciudad.includes(normalized)
    );
  });
}

async function searchClientsRemotely(term) {
  const normalized = String(term || '').trim();
  if (!normalized) return [];

  let indexUrl = '/clients';

  if (window.SALES_ROUTES?.clientIndex) {
    indexUrl = window.SALES_ROUTES.clientIndex;
  }

  try {
    const url = new URL(indexUrl, window.location.origin);
    url.searchParams.set('search', normalized);
    url.searchParams.set('per_page', '15');

    const res = await fetch(url.toString(), {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    });

    if (!res.ok) return [];

    const json = await res.json();
    if (Array.isArray(json)) return json;
    if (Array.isArray(json?.data)) return json.data;
    return [];
  } catch (error) {
    console.error('[POS] Error consultando clientes en servidor:', error);
    return [];
  }
}

function findExactClientInList(term, clients = []) {
  const normalized = normalizeClientTerm(term);
  if (!normalized || !Array.isArray(clients)) return null;

  return clients.find((client) => {
    const ident = normalizeClientTerm(client?.identificacion || '');
    const name = normalizeClientTerm(client?.business || client?.nombre || '');
    return ident === normalized || name === normalized;
  }) || null;
}

function applyClientSelection(client) {
  if (!client) return;

  const inputId = document.getElementById('client_id');
  const inputName = document.getElementById('cliente_nombre');
  const identEl = document.getElementById('cliente_identificacion');
  const quickSearch = document.getElementById('client_quick_search');

  const clientId = client.id;
  const clientName = client.business || client.nombre || 'Cliente seleccionado';
  const clientIdent = client.identificacion || '';

  if (isConsumidorFinalByIdent(clientIdent)) {
    setConsumidorFinalUI();
  } else {
    if (inputId) {
      inputId.value = clientId;
      inputId.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (inputName) inputName.textContent = clientName;
    if (identEl) identEl.textContent = clientIdent || 'Sin identificación';
  }

  if (quickSearch) quickSearch.value = '';
}

export function clearSelectedClient() {
  setConsumidorFinalUI();

  const quickSearch = document.getElementById('client_quick_search');
  if (quickSearch) quickSearch.value = '';
}

export function getSelectedClientSnapshot() {
  const inputId = document.getElementById('client_id');
  const inputName = document.getElementById('cliente_nombre');
  const identEl = document.getElementById('cliente_identificacion');
  const emailSelect = document.getElementById('cliente_email');
  const selectedOpt = emailSelect?.selectedOptions?.[0] || null;

  const clientId = inputId?.value ? String(inputId.value).trim() : '';
  const ident = identEl?.textContent?.trim() || '';

  return {
    clientId: clientId || null,
    name: inputName?.textContent?.trim() || '',
    ident,
    clientEmailId: emailSelect?.value ? String(emailSelect.value) : null,
    clientEmail: selectedOpt?.dataset?.email || selectedOpt?.text || null,
    isConsumidorFinal: !clientId || isConsumidorFinalByIdent(ident),
  };
}

export async function restoreClientSelection(snapshot = null) {
  const inputId = document.getElementById('client_id');
  const inputName = document.getElementById('cliente_nombre');
  const identEl = document.getElementById('cliente_identificacion');
  const quickSearch = document.getElementById('client_quick_search');

  if (quickSearch) quickSearch.value = '';

  if (!snapshot || snapshot.isConsumidorFinal || !snapshot.clientId) {
    clearSelectedClient();
    return;
  }

  if (inputId) inputId.value = String(snapshot.clientId);
  if (inputName) inputName.textContent = snapshot.name || 'Cliente seleccionado';
  if (identEl) identEl.textContent = snapshot.ident || 'Sin identificación';

  await loadClientEmails(snapshot.clientId, snapshot.clientEmailId || null);
}

function inferTipoIdentificacion(value) {
  const digits = String(value || '').replace(/\D+/g, '');
  if (digits.length === 10) return 'CEDULA';
  if (digits.length === 13) return 'RUC';
  return '';
}

function prefillCreateClientForm(prefill = {}) {
  const modal = document.getElementById('createClientModal');
  if (!modal) return;

  const tipoSelect = modal.querySelector('select[name="tipo"]');
  const tipoIdentSelect = modal.querySelector('select[name="tipo_identificacion"]');
  const identificacionInput = modal.querySelector('input[name="identificacion"]');
  const nombresInput = modal.querySelector('#create-nombres');
  const apellidosInput = modal.querySelector('#create-apellidos');
  const razonInput = modal.querySelector('#create-razon-social');
  const telefonoInput = modal.querySelector('input[name="telefono"]');
  const ciudadInput = modal.querySelector('input[name="ciudad"]');
  const direccionInput = modal.querySelector('input[name="direccion"]');

  const identificacion = String(prefill.identificacion || '').trim();
  const tipoIdentificacion = prefill.tipo_identificacion || inferTipoIdentificacion(identificacion);

  if (tipoSelect) tipoSelect.value = tipoIdentificacion === 'RUC' ? 'juridico' : 'natural';
  if (tipoIdentSelect && tipoIdentificacion) tipoIdentSelect.value = tipoIdentificacion;
  if (identificacionInput) identificacionInput.value = identificacion;
  if (nombresInput) nombresInput.value = '';
  if (apellidosInput) apellidosInput.value = '';
  if (razonInput) razonInput.value = '';
  if (telefonoInput) telefonoInput.value = '';
  if (ciudadInput) ciudadInput.value = '';
  if (direccionInput) direccionInput.value = '';

  tipoSelect?.dispatchEvent(new Event('change', { bubbles: true }));
  updateCreateBusiness();

  const focusTarget = tipoIdentificacion === 'RUC' ? razonInput : nombresInput;
  focusTarget?.focus();
}

function findBestClientMatch(term) {
  const normalized = normalizeClientTerm(term);
  if (!normalized) return { type: 'none' };

  const exactIdent = allClients.find((client) =>
    normalizeClientTerm(client.identificacion || '') === normalized
  );
  if (exactIdent) return { type: 'single', client: exactIdent };

  const exactName = allClients.find((client) =>
    normalizeClientTerm(client.business || client.nombre || '') === normalized
  );
  if (exactName) return { type: 'single', client: exactName };

  const filtered = getFilteredClients(term);
  if (filtered.length === 1) return { type: 'single', client: filtered[0] };
  if (filtered.length > 1) return { type: 'multiple', clients: filtered };

  return { type: 'none' };
}

function openSearchModalWithResults(term, clients) {
  const searchModal = document.getElementById('client-modal');
  const searchInput = document.getElementById('client_search_term');
  if (!searchModal) return;

  searchModal.classList.remove('hidden');
  if (searchInput) searchInput.value = term;
  renderClientResults(clients);
}



/**
 * Helpers globales para el modal de crear cliente (usados por los onclick del Blade).
 */
function updateCreateBusiness() {
    const modal = document.getElementById('createClientModal');
    if (!modal) return;

    const tipoSelect = modal.querySelector('select[name="tipo"]');
    const nombres = modal.querySelector('#create-nombres');
    const apellidos = modal.querySelector('#create-apellidos');
    const razon = modal.querySelector('#create-razon-social');
    const businessInput = modal.querySelector('#create-business');

    if (!tipoSelect || !businessInput) return;

    const tipo = tipoSelect.value;
    let business = '';

    if (tipo === 'juridico') {
        business = (razon?.value || '').trim();
    } else {
        const nom = (nombres?.value || '').trim();
        const ape = (apellidos?.value || '').trim();
        business = `${nom} ${ape}`.trim();
    }

    businessInput.value = business;
}

function setupCreateClientForm() {
    const modal = document.getElementById('createClientModal');
    if (!modal) return;

    // Evitar inicializar dos veces
    if (createClientFormInitialized) return;
    createClientFormInitialized = true;

    const tipoSelect      = modal.querySelector('select[name="tipo"]');
    const tipoIdSelect    = modal.querySelector('select[name="tipo_identificacion"]');
    const naturalFields   = modal.querySelector('#create-natural-fields');
    const juridicoFields  = modal.querySelector('#create-juridico-fields');
    const form            = modal.querySelector('#createClientForm'); // <form id="createClientForm">

    // ----- VISIBILIDAD NATURAL / JURÍDICO -----
    if (tipoSelect) {
        const syncVisibility = () => {
            const isJuridico = tipoSelect.value === 'juridico';
            if (naturalFields)  naturalFields.classList.toggle('hidden', isJuridico);
            if (juridicoFields) juridicoFields.classList.toggle('hidden', !isJuridico);

            if (tipoIdSelect) {
                const cedulaOption = tipoIdSelect.querySelector('option[value="CEDULA"]');
                const pasaporteOption = tipoIdSelect.querySelector('option[value="PASAPORTE"]');
                if (cedulaOption) {
                    cedulaOption.hidden = isJuridico;
                    cedulaOption.disabled = isJuridico;
                }
                if (pasaporteOption) {
                    pasaporteOption.hidden = isJuridico;
                    pasaporteOption.disabled = isJuridico;
                }

                if (isJuridico && ['CEDULA', 'PASAPORTE'].includes(tipoIdSelect.value)) {
                    const rucOption = tipoIdSelect.querySelector('option[value="RUC"]');
                    tipoIdSelect.value = rucOption ? 'RUC' : '';
                }
            }

            updateCreateBusiness();
        };

        tipoSelect.addEventListener('change', syncVisibility);

        ['#create-nombres', '#create-apellidos', '#create-razon-social'].forEach((sel) => {
            const el = modal.querySelector(sel);
            if (el) el.addEventListener('input', updateCreateBusiness);
        });

        syncVisibility();
    }

    // ----- SUBMIT AJAX DEL FORMULARIO -----
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const action = form.getAttribute('action') || '/clients';
            const formData = new FormData(form);

            try {
                const res = await fetch(action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': window.CSRF_TOKEN,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                    credentials: 'same-origin',
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok || !data.ok) {
                    // ❌ Error al crear cliente
                    const msg =
                        data.message ||
                        'Ocurrió un error al crear el cliente. Revisa los datos ingresados.';
                    showSaleAlert(msg, true);
                    return;
                }

                // ✅ Cliente creado con éxito
                const newClient = data.data || {};
                // Lo agregamos al cache para que aparezca en la búsqueda
                if (newClient.id) {
                    allClients.push(newClient);
                    allClientsLoaded = true;
                }

                // Seleccionar automáticamente el cliente recién creado en el POS
                const inputId   = document.getElementById('client_id');
                const inputName = document.getElementById('cliente_nombre');
                const identEl   = document.getElementById('cliente_identificacion');

                if (inputId) {
                inputId.value = newClient.id;
                inputId.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (inputName) {
                inputName.textContent = newClient.business || newClient.nombre || 'Cliente creado';
                }
                if (identEl) {
                identEl.textContent = newClient.identificacion || 'Sin identificación';
                }



                // Cargar correos del nuevo cliente
                if (newClient.id) {
                    await loadClientEmails(newClient.id);
                }

                // Cerrar modal
                modal.classList.add('hidden');

                // Limpiar formulario
                form.reset();

                showSaleAlert(
                    data.message || 'Cliente creado correctamente',
                    false
                );
            } catch (error) {
                console.error('[POS] Error al crear cliente vía AJAX:', error);
                showSaleAlert(
                    'Ocurrió un error inesperado al crear el cliente.',
                    true
                );
            }
        });
    }
}


// Funciones globales usadas por el Blade de create client
window.openCreateModal = function (prefill = null) {
    const modal = document.getElementById('createClientModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    setupCreateClientForm();
    if (prefill) {
      prefillCreateClientForm(prefill);
    }
};

window.closeCreateModal = function () {
    const modal = document.getElementById('createClientModal');
    if (!modal) return;
    modal.classList.add('hidden');
};

window.addCreateEmailInput = function () {
    const wrapper = document.getElementById('create-emails-wrapper');
    if (!wrapper) return;

    const row = document.createElement('div');
    row.className = 'flex gap-2';
    row.innerHTML = `
        <input
            type="email"
            name="emails[]"
            placeholder="correo@ejemplo.com"
            class="flex-1 border-gray-300 rounded-md shadow-sm text-sm"
        >
        <button
            type="button"
            onclick="removeCreateEmailInput(this)"
            class="px-2 py-1 text-xs rounded-md border border-gray-300 text-red-600 hover:bg-red-50"
        >
            −
        </button>
    `;
    wrapper.appendChild(row);
};

window.removeCreateEmailInput = function (btn) {
    const row = btn.closest('.flex');
    if (row && row.parentNode) {
        row.parentNode.removeChild(row);
    }
};

/**
 * Inicializa todo el flujo de selección/creación de clientes en el POS.
 */
export function initClientSelector() {
    console.log('[POS] initClientSelector() ejecutado');

    const searchModal = document.getElementById('client-modal');        // modal de búsqueda
    const btnCreate   = document.getElementById('btn-open-client-modal'); // botón +
    const createModal = document.getElementById('createClientModal');   // modal crear
    const clientIdInput = document.getElementById('client_id');
    const quickSearch = document.getElementById('client_quick_search');

    if (searchModal) {
        searchModal.querySelectorAll('[data-client-close]').forEach((btn) => {
            btn.addEventListener('click', () => searchModal.classList.add('hidden'));
        });
    }

    if (btnCreate && createModal) {
        btnCreate.addEventListener('click', () => {
            console.log('[POS] Click en crear cliente');
            window.openCreateModal();
        });
    }

    const searchInput = document.getElementById('client_search_term');
    if (searchInput) {
        searchInput.addEventListener(
            'input',
            debounce(async (e) => {
                const term = e.target.value || '';
                await loadAllClients();
                filterAndRenderClients(term);
            }, 200)
        );
    }

    const tbody = document.getElementById('client_results');
    if (tbody) {
        tbody.addEventListener('click', (e) => {
            const tr = e.target.closest('tr[data-client-id]');
            if (!tr) return;
            applyClientSelection({
              id: tr.dataset.clientId,
              business: tr.dataset.clientName,
              identificacion: tr.dataset.clientIdentificacion || '',
            });

            if (searchModal) {
                searchModal.classList.add('hidden');
            }
        });
        selectDefaultConsumidorFinalIfEmpty();
    }

    if (quickSearch) {
      quickSearch.addEventListener('keydown', async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();

        const term = quickSearch.value.trim();
        if (!term) return;

        await loadAllClients();
        const result = findBestClientMatch(term);

        if (result.type === 'single' && result.client) {
          applyClientSelection(result.client);
          return;
        }

        if (result.type === 'multiple' && Array.isArray(result.clients)) {
          openSearchModalWithResults(term, result.clients);
          return;
        }

        const remoteClients = await searchClientsRemotely(term);
        const remoteExact = findExactClientInList(term, remoteClients);

        if (remoteExact) {
          if (!allClients.some((client) => String(client?.id) === String(remoteExact?.id))) {
            allClients.push(remoteExact);
          }
          applyClientSelection(remoteExact);
          return;
        }

        if (remoteClients.length === 1) {
          const [singleRemote] = remoteClients;
          if (!allClients.some((client) => String(client?.id) === String(singleRemote?.id))) {
            allClients.push(singleRemote);
          }
          applyClientSelection(singleRemote);
          return;
        }

        if (remoteClients.length > 1) {
          openSearchModalWithResults(term, remoteClients);
          return;
        }

        window.openCreateModal({
          identificacion: term,
        });
      });
    }

    if (clientIdInput) {
        clientIdInput.addEventListener('change', (e) => {
            const id = e.target.value;
            console.log('[POS] Evento change en #client_id, nuevo valor =', id);
            if (id) {
                loadClientEmails(id);
            } else {
                const select = document.getElementById('cliente_email');
                const resumen = document.getElementById('cliente_email_resumen');
                if (select) select.innerHTML = '<option value="">Sin correo (Consumidor Final)</option>';
                if (resumen) resumen.textContent = 'Sin correo seleccionado';
            }

        });
    }

    const emailSelect = document.getElementById('cliente_email');
    const emailResumen = document.getElementById('cliente_email_resumen');

    if (emailSelect && emailResumen) {
    emailSelect.addEventListener('change', () => {
        const opt = emailSelect.selectedOptions?.[0];
        const email = opt?.text || '';
        emailResumen.textContent = opt?.value ? `Enviar factura a: ${email}` : 'Sin correo seleccionado';
    });
    }

}
