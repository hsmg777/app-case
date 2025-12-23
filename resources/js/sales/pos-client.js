// resources/js/sales/pos-client.js
import { showSaleAlert } from './pos-utils';

console.log('[POS] pos-client.js cargado');
let createClientFormInitialized = false;


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
    const nombreEl      = document.getElementById('cliente_nombre');
    const identEl       = document.getElementById('cliente_identificacion');

    if (!clientIdInput || clientIdInput.value) {
        return;
    }

    try {
        await loadAllClients();

        if (!allClients.length) {
            console.warn('[POS] No hay clientes para seleccionar por defecto');
            return;
        }

        const defaultClient = allClients.find((c) =>
            (c.business || '').toString().trim().toLowerCase() === 'consumidor final'
        );

        if (!defaultClient) {
            console.warn('[POS] No se encontró cliente con business "Consumidor Final"');
            return;
        }

        console.log('[POS] Cliente por defecto (Consumidor Final) encontrado:', defaultClient);

        clientIdInput.value = defaultClient.id;
        clientIdInput.dispatchEvent(new Event('change', { bubbles: true }));

        if (nombreEl) {
            nombreEl.textContent = defaultClient.business || 'Consumidor final';
        }
        if (identEl) {
            identEl.textContent = defaultClient.identificacion || 'Sin identificación';
        }

        
        if (defaultClient.id) {
            loadClientEmails(defaultClient.id);
        }
    } catch (e) {
        console.error('[POS] Error seleccionando cliente por defecto "Consumidor Final":', e);
    }
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
    const normalized = term.trim().toLowerCase();

    if (!normalized) {
        renderClientResults(allClients);
        return;
    }

    const filtered = allClients.filter((c) => {
        const name = (c.business || '').toLowerCase();
        const identificacion = (c.identificacion || '');
        const telefono = (c.telefono || '');
        const ciudad = (c.ciudad || '').toLowerCase();

        return (
            name.includes(normalized) ||
            identificacion.includes(normalized) ||
            telefono.includes(normalized) ||
            ciudad.includes(normalized)
        );
    });

    renderClientResults(filtered);
}

/**
 * Carga los emails del cliente para el select de correo.
 */
async function loadClientEmails(clientId) {
  const select  = document.getElementById('cliente_email');
  const resumen = document.getElementById('cliente_email_resumen');

  console.log('[POS] loadClientEmails() llamado con clientId =', clientId);
  console.log('[POS] select encontrado?', !!select);

  if (!select || !clientId) {
    console.warn('[POS] No hay <select id="cliente_email"> o clientId vacío');
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

    emails.forEach(({ id, email }) => {
      const opt = new Option(email, String(id));
      opt.dataset.email = email; 
      select.add(opt);
      if (!first) first = { id, email };
    });

    if (first) {
      select.value = String(first.id);
      if (resumen) resumen.textContent = `Enviar factura a: ${first.email}`;
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
    const naturalFields   = modal.querySelector('#create-natural-fields');
    const juridicoFields  = modal.querySelector('#create-juridico-fields');
    const form            = modal.querySelector('#createClientForm'); // <form id="createClientForm">

    // ----- VISIBILIDAD NATURAL / JURÍDICO -----
    if (tipoSelect) {
        const syncVisibility = () => {
            const isJuridico = tipoSelect.value === 'juridico';
            if (naturalFields)  naturalFields.classList.toggle('hidden', isJuridico);
            if (juridicoFields) juridicoFields.classList.toggle('hidden', !isJuridico);
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
                    inputName.textContent =
                        newClient.business || newClient.nombre || 'Cliente creado';
                }
                if (identEl) {
                    identEl.textContent =
                        newClient.identificacion || 'Sin identificación';
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
window.openCreateModal = function () {
    const modal = document.getElementById('createClientModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    setupCreateClientForm();
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
    const btnSearch   = document.getElementById('btn-search-client');   // lupa
    const btnCreate   = document.getElementById('btn-open-client-modal'); // botón +
    const createModal = document.getElementById('createClientModal');   // modal crear
    const clientIdInput = document.getElementById('client_id');

    // 🔍 Abrir modal de búsqueda
    if (btnSearch && searchModal) {
        btnSearch.addEventListener('click', async () => {
            console.log('[POS] Click en buscar cliente');
            searchModal.classList.remove('hidden');

            await loadAllClients();
            renderClientResults(allClients);
        });

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

            const clientId   = tr.dataset.clientId;
            const clientName = tr.dataset.clientName;
            const clientIdent = tr.dataset.clientIdentificacion || '';

            console.log('[POS] Cliente seleccionado en tabla:', {
                clientId,
                clientName,
                clientIdent,
            });

            const inputId   = document.getElementById('client_id');
            const inputName = document.getElementById('cliente_nombre');
            const identEl   = document.getElementById('cliente_identificacion');

            if (inputId) {
                inputId.value = clientId;
                inputId.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (inputName) inputName.textContent = clientName || 'Cliente seleccionado';
            if (identEl)   identEl.textContent = clientIdent || 'Sin identificación';

            if (searchModal) {
                searchModal.classList.add('hidden');
            }

            if (clientId) {
                console.log('[POS] Llamando loadClientEmails desde click en fila');
                loadClientEmails(clientId);
            } else {
                showSaleAlert('No se pudo obtener el ID del cliente seleccionado.', true);
            }
        });
        selectDefaultConsumidorFinalIfEmpty();
    }

    if (clientIdInput) {
        clientIdInput.addEventListener('change', (e) => {
            const id = e.target.value;
            console.log('[POS] Evento change en #client_id, nuevo valor =', id);
            if (id) {
                loadClientEmails(id);
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
