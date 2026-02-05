<x-app-layout>
    {{-- HEADER --}}
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Clientes') }}
            </h2>

            <button 
                onclick="window.location.href='{{ route('dashboard') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center"
            >
                <x-heroicon-s-arrow-left class="w-6 h-6 mr-1" />
                Atrás
            </button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">

            {{-- FILTROS --}}
            <form
                id="clientsFilterForm"
                method="GET"
                action="{{ route('clients.index') }}"
                class="mb-4 bg-white rounded-lg shadow-sm border border-gray-200 p-4"
            >
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    {{-- Buscar --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Buscar</label>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Nombre, identificación, teléfono..."
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                        >
                    </div>

                    {{-- Tipo --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo de cliente</label>
                        <select
                            name="tipo"
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                        >
                            <option value="">Todos</option>
                            <option value="natural" {{ ($filters['tipo'] ?? '') === 'natural' ? 'selected' : '' }}>Natural</option>
                            <option value="juridico" {{ ($filters['tipo'] ?? '') === 'juridico' ? 'selected' : '' }}>Jurídico</option>
                        </select>
                    </div>

                    {{-- Estado --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Estado</label>
                        <select
                            name="estado"
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                        >
                            <option value="">Todos</option>
                            <option value="activo" {{ ($filters['estado'] ?? '') === 'activo' ? 'selected' : '' }}>Activo</option>
                            <option value="inactivo" {{ ($filters['estado'] ?? '') === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                        </select>
                    </div>

                    {{-- Ciudad --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Ciudad</label>
                        <input
                            type="text"
                            name="ciudad"
                            value="{{ $filters['ciudad'] ?? '' }}"
                            placeholder="Ciudad"
                            class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                        >
                    </div>
                </div>

                {{-- BOTÓN CREAR CLIENTE --}}
                <div class="mt-3 flex justify-end gap-2">
                    <a
                        href="{{ route('clients.export', request()->query()) }}"
                        class="bg-blue-50 hover:bg-blue-100 text-blue-700 px-4 py-2 rounded-md shadow-md text-xs flex items-center gap-2"
                    >
                        Exportar Excel
                    </a>
                    <button
                        type="button"
                        onclick="openCreateModal()"
                        class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-md shadow-md text-xs flex items-center gap-2"
                    >
                        + Nuevo Cliente
                    </button>
                </div>
            </form>

            {{-- TABLA --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Identificación</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Business</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Tipo</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Teléfono</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Ciudad</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Estado</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Emails</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($clients as $client)
                            <tr>
                                <td class="px-4 py-2">
                                    <div class="flex flex-col">
                                        <span class="text-xs text-gray-500">
                                            {{ $client->tipo_identificacion }}
                                        </span>
                                        <span class="font-mono text-sm text-gray-800">
                                            {{ $client->identificacion }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="font-medium text-gray-900">{{ $client->business }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                                        {{ $client->tipo === 'natural' ? 'bg-green-50 text-green-700' : 'bg-indigo-50 text-indigo-700' }}">
                                        {{ ucfirst($client->tipo) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-gray-800 text-sm">{{ $client->telefono ?? '-' }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-gray-800 text-sm">{{ $client->ciudad ?? '-' }}</span>
                                </td>
                                <td class="px-4 py-2">
                                    @if ($client->estado === 'activo')
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-emerald-50 text-emerald-700">
                                            Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-red-50 text-red-700">
                                            Inactivo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @php
                                        $emails = $client->emails ?? collect();
                                    @endphp
                                    @if($emails->count() === 0)
                                        <span class="text-xs text-gray-500">Sin correos</span>
                                    @else
                                        <div class="flex flex-col text-xs text-gray-800">
                                            <span>{{ $emails->first()->email }}</span>
                                            @if($emails->count() > 1)
                                                <span class="text-[11px] text-gray-500">
                                                    + {{ $emails->count() - 1 }} correo(s) más
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            onclick="openViewModal({{ $client->id }})"
                                            class="px-2 py-1 text-[11px] rounded-md border border-gray-200 text-gray-700 hover:bg-gray-100"
                                        >
                                            Ver
                                        </button>
                                        <button
                                            type="button"
                                            onclick="openEditModal({{ $client->id }})"
                                            class="px-2 py-1 text-[11px] rounded-md bg-blue-50 text-blue-700 hover:bg-blue-100"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            onclick="confirmDelete({{ $client->id }})"
                                            class="px-2 py-1 text-[11px] rounded-md bg-red-50 text-red-700 hover:bg-red-100"
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">
                                    No se encontraron clientes con los filtros actuales.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- PAGINACIÓN --}}
                <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
                    {{ $clients->appends(request()->query())->links() }}
                </div>
            </div>

            {{-- FORMULARIO GLOBAL PARA DELETE --}}
            <form id="deleteClientForm" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>

        </div>
    </div>

    {{-- MODALES EN ARCHIVOS APARTE --}}
    @include('clients.modals.create')
    @include('clients.modals.edit')
    @include('clients.modals.show')

   
        <script>
    // ---------- HELPERS EMAILS MODAL CREAR ----------
    function buildCreateEmailRow(value = "") {
        const row = document.createElement("div");
        row.className = "flex gap-2";

        const input = document.createElement("input");
        input.type = "email";
        input.name = "emails[]";
        input.placeholder = "correo@ejemplo.com";
        input.value = value;
        input.className = "flex-1 border-gray-300 rounded-md shadow-sm text-sm";

        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = "−";
        btn.className = "px-2 py-1 text-xs rounded-md border border-gray-300 text-red-600 hover:bg-red-50";
        btn.onclick = function () {
            removeCreateEmailInput(btn);
        };

        row.appendChild(input);
        row.appendChild(btn);
        return row;
    }

    function addCreateEmailInput() {
        const wrapper = document.getElementById("create-emails-wrapper");
        if (!wrapper) return;
        wrapper.appendChild(buildCreateEmailRow(""));
    }

    function removeCreateEmailInput(button) {
        const wrapper = document.getElementById("create-emails-wrapper");
        if (!wrapper) return;

        const row = button.closest("div");
        if (row) wrapper.removeChild(row);

        // Siempre dejamos al menos un input
        if (wrapper.children.length === 0) {
            wrapper.appendChild(buildCreateEmailRow(""));
        }
    }

    // ---------- MODALES (asegurando flex/hidden) ----------
    function openCreateModal() {
        const modal = document.getElementById("createClientModal");
        if (!modal) return;

        const form = modal.querySelector("form");
        if (form) form.reset();

        // reset campo business
        const businessInput = document.getElementById("create-business");
        if (businessInput) businessInput.value = "";

        // reset emails → un solo input
        const wrapper = document.getElementById("create-emails-wrapper");
        if (wrapper) {
            wrapper.innerHTML = "";
            wrapper.appendChild(buildCreateEmailRow(""));
        }

        // reset tipo a natural por defecto y mostrar campos correctos
        const tipoSelect = modal.querySelector('select[name="tipo"]');
        if (tipoSelect) {
            tipoSelect.value = "natural";
        }
        if (typeof toggleCreatePersonaFields === "function") {
            toggleCreatePersonaFields();
        }

        modal.classList.remove("hidden");
        modal.classList.add("flex");
    }

    function closeCreateModal() {
        const modal = document.getElementById('createClientModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    async function openEditModal(id) {
        const modal = document.getElementById('editClientModal');
        if (!modal) return;

        try {
            const url = "{{ route('clients.show', ':id') }}".replace(':id', id);
            const res = await fetch(url);

            if (!res.ok) {
                throw new Error('No se pudo obtener la información del cliente');
            }

            const client = await res.json();

            const form = modal.querySelector('#editClientForm');
            if (form) {
                form.reset();
                form.action = "{{ route('clients.update', ':id') }}".replace(':id', id);

                // básicos
                form.querySelector('[name="tipo_identificacion"]').value = client.tipo_identificacion ?? '';
                form.querySelector('[name="identificacion"]').value = client.identificacion ?? '';
                form.querySelector('[name="telefono"]').value = client.telefono ?? '';
                form.querySelector('[name="direccion"]').value = client.direccion ?? '';
                form.querySelector('[name="ciudad"]').value = client.ciudad ?? '';
                form.querySelector('[name="estado"]').value = client.estado ?? 'activo';

                // tipo de cliente
                const tipoSelect = form.querySelector('select[name="tipo"]');
                if (tipoSelect) {
                    tipoSelect.value = client.tipo ?? 'natural';
                }

                // business oculto + descomposición en nombres/apellidos o razón social
                const businessHidden = document.getElementById('edit-business');
                const nombresInput = document.getElementById('edit-nombres');
                const apellidosInput = document.getElementById('edit-apellidos');
                const razonInput = document.getElementById('edit-razon-social');

                const business = client.business ?? '';
                if (businessHidden) businessHidden.value = business;

                if (client.tipo === 'juridico') {
                    if (razonInput) razonInput.value = business;
                    if (nombresInput) nombresInput.value = '';
                    if (apellidosInput) apellidosInput.value = '';
                } else {
                    // intento decente de separar nombres/apellidos
                    const parts = business.trim().split(/\s+/);
                    let nombres = '';
                    let apellidos = '';
                    if (parts.length === 1) {
                        nombres = parts[0];
                    } else if (parts.length > 1) {
                        apellidos = parts.pop();
                        nombres = parts.join(' ');
                    }
                    if (nombresInput) nombresInput.value = nombres;
                    if (apellidosInput) apellidosInput.value = apellidos;
                    if (razonInput) razonInput.value = '';
                }

                if (typeof toggleEditPersonaFields === 'function') {
                    toggleEditPersonaFields();
                }

                // Emails
                const emailsWrapper = document.getElementById('edit-emails-wrapper');
                if (emailsWrapper) {
                    emailsWrapper.innerHTML = '';

                    if (client.emails && Array.isArray(client.emails) && client.emails.length > 0) {
                        client.emails.forEach(e => {
                            emailsWrapper.appendChild(buildEditEmailRow(e.email));
                        });
                    } else {
                        emailsWrapper.appendChild(buildEditEmailRow(""));
                    }
                }
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        } catch (error) {
            console.error(error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo cargar la información del cliente.',
            });
        }
    }


    // ---------- HELPERS EMAILS MODAL EDITAR ----------
    function buildEditEmailRow(value = "") {
        const row = document.createElement("div");
        row.className = "flex gap-2";

        const input = document.createElement("input");
        input.type = "email";
        input.name = "emails[]";
        input.placeholder = "correo@ejemplo.com";
        input.value = value;
        input.className = "flex-1 border-gray-300 rounded-md shadow-sm text-sm";

        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = "−";
        btn.className = "px-2 py-1 text-xs rounded-md border border-gray-300 text-red-600 hover:bg-red-50";
        btn.onclick = function () {
            removeEditEmailInput(btn);
        };

        row.appendChild(input);
        row.appendChild(btn);
        return row;
    }

    function addEditEmailInput() {
        const wrapper = document.getElementById("edit-emails-wrapper");
        if (!wrapper) return;
        wrapper.appendChild(buildEditEmailRow(""));
    }

    function removeEditEmailInput(button) {
        const wrapper = document.getElementById("edit-emails-wrapper");
        if (!wrapper) return;

        const row = button.closest("div");
        if (row) wrapper.removeChild(row);

        // Siempre dejamos al menos un input
        if (wrapper.children.length === 0) {
            wrapper.appendChild(buildEditEmailRow(""));
        }
    }


    function closeEditModal() {
        const modal = document.getElementById('editClientModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    async function openViewModal(id) {
        const modal = document.getElementById('viewClientModal');
        if (!modal) return;

        try {
            const url = "{{ route('clients.show', ':id') }}".replace(':id', id);
            const res = await fetch(url);

            if (!res.ok) {
                throw new Error('No se pudo obtener la información del cliente');
            }

            const client = await res.json();

            // Rellenar spans/inputs de solo lectura dentro del modal
            modal.querySelector('[data-field="identificacion"]').textContent =
                (client.tipo_identificacion ?? '') + ' - ' + (client.identificacion ?? '');
            modal.querySelector('[data-field="business"]').textContent =
                client.business ?? '';
            modal.querySelector('[data-field="tipo"]').textContent =
                client.tipo ? client.tipo.charAt(0).toUpperCase() + client.tipo.slice(1) : '';
            modal.querySelector('[data-field="telefono"]').textContent =
                client.telefono ?? '-';
            modal.querySelector('[data-field="direccion"]').textContent =
                client.direccion ?? '-';
            modal.querySelector('[data-field="ciudad"]').textContent =
                client.ciudad ?? '-';
            modal.querySelector('[data-field="estado"]').textContent =
                client.estado ?? '-';

            const emailsContainer = modal.querySelector('[data-field="emails"]');
            if (emailsContainer) {
                emailsContainer.innerHTML = '';
                if (client.emails && Array.isArray(client.emails) && client.emails.length > 0) {
                    client.emails.forEach(e => {
                        const li = document.createElement('li');
                        li.textContent = e.email;
                        emailsContainer.appendChild(li);
                    });
                } else {
                    emailsContainer.innerHTML = '<li class="text-xs text-gray-500">Sin correos registrados</li>';
                }
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        } catch (error) {
            console.error(error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo cargar la información del cliente.',
            });
        }
    }

    function closeViewModal() {
        const modal = document.getElementById('viewClientModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // ---------- ELIMINAR CON SWEETALERT ----------
    function confirmDelete(id) {
        Swal.fire({
            title: '¿Eliminar cliente?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e02424',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('deleteClientForm');
                if (!form) return;

                form.action = "{{ route('clients.destroy', ':id') }}".replace(':id', id);
                form.submit();
            }
        });
    }

    // ---------- FILTROS + LÓGICA MODAL CREAR (DOMContentLoaded) ----------
    document.addEventListener('DOMContentLoaded', () => {
        // ---- filtros "al toque" ----
        const form = document.getElementById('clientsFilterForm');
        if (form) {
            let debounceTimer = null;

            const autoSubmit = () => {
                if (debounceTimer) clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    form.submit();
                }, 400); // pequeño delay para no disparar por cada tecla muy rápido
            };

            const searchInput = form.querySelector('input[name="search"]');
            const ciudadInput = form.querySelector('input[name="ciudad"]');
            const tipoSelectFilter = form.querySelector('select[name="tipo"]');
            const estadoSelect = form.querySelector('select[name="estado"]');

            if (searchInput) searchInput.addEventListener('input', autoSubmit);
            if (ciudadInput) ciudadInput.addEventListener('input', autoSubmit);
            if (tipoSelectFilter) tipoSelectFilter.addEventListener('change', autoSubmit);
            if (estadoSelect) estadoSelect.addEventListener('change', autoSubmit);
        }

        // ---- lógica tipo persona (natural / jurídico) en modal crear ----
        const modal = document.getElementById("createClientModal");
        if (!modal) return;

        const tipoSelectPersona = modal.querySelector('select[name="tipo"]');
        const naturalFields = document.getElementById("create-natural-fields");
        const juridicoFields = document.getElementById("create-juridico-fields");
        const nombresInput = document.getElementById("create-nombres");
        const apellidosInput = document.getElementById("create-apellidos");
        const razonInput = document.getElementById("create-razon-social");
        const businessInput = document.getElementById("create-business");
        const createForm = modal.querySelector("form");

        // función global para que la use openCreateModal()
        window.toggleCreatePersonaFields = function () {
            if (!tipoSelectPersona) return;
            if (tipoSelectPersona.value === "juridico") {
                if (naturalFields) naturalFields.classList.add("hidden");
                if (juridicoFields) juridicoFields.classList.remove("hidden");
            } else {
                if (naturalFields) naturalFields.classList.remove("hidden");
                if (juridicoFields) juridicoFields.classList.add("hidden");
            }
        };

        if (tipoSelectPersona) {
            tipoSelectPersona.addEventListener("change", toggleCreatePersonaFields);
            // estado inicial
            toggleCreatePersonaFields();
        }

        if (createForm && businessInput) {
            createForm.addEventListener("submit", function () {
                if (tipoSelectPersona && tipoSelectPersona.value === "juridico") {
                    businessInput.value = razonInput ? razonInput.value.trim() : "";
                } else {
                    const nombres = nombresInput ? nombresInput.value.trim() : "";
                    const apellidos = apellidosInput ? apellidosInput.value.trim() : "";
                    businessInput.value = (nombres + " " + apellidos).trim();
                }
            });
        }

            // ---- lógica tipo persona (natural / jurídico) en modal EDITAR ----
        const editModal = document.getElementById("editClientModal");
        if (editModal) {
            const tipoSelectEdit = editModal.querySelector('select[name="tipo"]');
            const naturalFieldsEdit = document.getElementById("edit-natural-fields");
            const juridicoFieldsEdit = document.getElementById("edit-juridico-fields");
            const nombresInputEdit = document.getElementById("edit-nombres");
            const apellidosInputEdit = document.getElementById("edit-apellidos");
            const razonInputEdit = document.getElementById("edit-razon-social");
            const businessInputEdit = document.getElementById("edit-business");
            const editForm = document.getElementById("editClientForm");

            window.toggleEditPersonaFields = function () {
                if (!tipoSelectEdit) return;
                if (tipoSelectEdit.value === "juridico") {
                    if (naturalFieldsEdit) naturalFieldsEdit.classList.add("hidden");
                    if (juridicoFieldsEdit) juridicoFieldsEdit.classList.remove("hidden");
                } else {
                    if (naturalFieldsEdit) naturalFieldsEdit.classList.remove("hidden");
                    if (juridicoFieldsEdit) juridicoFieldsEdit.classList.add("hidden");
                }
            };

            if (tipoSelectEdit) {
                tipoSelectEdit.addEventListener("change", toggleEditPersonaFields);
                // estado inicial
                toggleEditPersonaFields();
            }

            if (editForm && businessInputEdit) {
                editForm.addEventListener("submit", function () {
                    if (tipoSelectEdit && tipoSelectEdit.value === "juridico") {
                        businessInputEdit.value = razonInputEdit ? razonInputEdit.value.trim() : "";
                    } else {
                        const nombres = nombresInputEdit ? nombresInputEdit.value.trim() : "";
                        const apellidos = apellidosInputEdit ? apellidosInputEdit.value.trim() : "";
                        businessInputEdit.value = (nombres + " " + apellidos).trim();
                    }
                });
            }
        }

    });

    // ---------- ALERTAS GLOBALES (SWEETALERT) ----------
    @if (session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: @json(session('success')),
            timer: 2500,
            showConfirmButton: false,
        });
    @endif

    @if ($errors->any())
        let errorHtml = '<ul style="text-align:left;">';
        @foreach ($errors->all() as $error)
            errorHtml += '<li>{{ $error }}<\/li>';
        @endforeach
        errorHtml += '</ul>';

        Swal.fire({
            icon: 'error',
            title: 'Hay errores en el formulario',
            html: errorHtml,
        });
    @endif
</script>


</x-app-layout>
