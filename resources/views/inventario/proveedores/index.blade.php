<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Proveedores') }}
            </h2>

            <button
                type="button"
                onclick="window.location.href='{{ route('proveedores.menu') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center"
            >
                <x-heroicon-s-arrow-left class="w-6 h-6 mr-1" />
                Volver al módulo
            </button>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">

            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-blue-900">Listado de proveedores</h3>

                <button type="button"
                        onclick="ProveedoresModal.openCreate()"
                        class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded shadow flex items-center text-sm">
                    <x-heroicon-s-plus class="w-4 h-4 mr-1" />
                    Nuevo proveedor
                </button>
            </div>

            <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
                <div class="flex justify-between mb-3">
                    <input id="buscar-proveedor"
                           type="text"
                           placeholder="Buscar por nombre, RUC o contacto..."
                           class="border rounded px-3 py-2 text-sm w-1/2"
                           oninput="filtrarProveedores()">

                    <span id="total-proveedores" class="text-xs text-gray-500"></span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600 uppercase text-xs">
                                <th class="px-3 py-2 text-left">Nombre</th>
                                <th class="px-3 py-2 text-left">RUC</th>
                                <th class="px-3 py-2 text-left">Contacto</th>
                                <th class="px-3 py-2 text-left">Teléfono</th>
                                <th class="px-3 py-2 text-left">Email</th>
                                <th class="px-3 py-2 text-center">Estado</th>
                                <th class="px-3 py-2 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-proveedores"></tbody>
                    </table>
                </div>

                <div id="sin-proveedores" class="text-center text-gray-400 text-sm py-6 hidden">
                    No hay proveedores registrados todavía.
                </div>
            </div>
        </div>
    </div>

    {{-- ✅ IMPORTANTE: ESTE INCLUDE ES EL QUE DEBE CONTENER modal-create y modal-edit --}}
    @include('inventario.proveedores.modals')

    <script>
        const PROVEEDORES_BASE = "{{ url('inventario/proveedores') }}";
        let TODOS_PROVEEDORES = [];

        // ✅ Namespace único para evitar choques con otros módulos
        window.ProveedoresModal = {
            openCreate() {
                const modal = document.getElementById('modal-create');
                if (!modal) {
                    console.error('No existe #modal-create en el DOM. Revisa resources/views/inventario/proveedores/modals.blade.php');
                    return;
                }
                modal.classList.remove('hidden');
            },
            closeCreate() {
                const modal = document.getElementById('modal-create');
                if (!modal) return;

                modal.classList.add('hidden');

                ['c-nombre','c-ruc','c-telefono','c-email','c-direccion','c-contacto'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });

                const chk = document.getElementById('c-activo');
                if (chk) chk.checked = true;
            },
            openEdit(id) {
                openEditModal(id); // reutiliza tu lógica existente
            },
            closeEdit() {
                const modal = document.getElementById('modal-edit');
                if (!modal) return;
                modal.classList.add('hidden');
            },
        };

        document.addEventListener('DOMContentLoaded', () => {
            cargarProveedores();

            // Cerrar al hacer click fuera
            document.addEventListener('click', (e) => {
                const create = document.getElementById('modal-create');
                const edit   = document.getElementById('modal-edit');

                if (create && !create.classList.contains('hidden') && e.target === create) window.ProveedoresModal.closeCreate();
                if (edit && !edit.classList.contains('hidden') && e.target === edit) window.ProveedoresModal.closeEdit();
            });

            // Cerrar con ESC
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                const create = document.getElementById('modal-create');
                const edit   = document.getElementById('modal-edit');
                if (create && !create.classList.contains('hidden')) window.ProveedoresModal.closeCreate();
                if (edit && !edit.classList.contains('hidden')) window.ProveedoresModal.closeEdit();
            });

            // 🔍 Debug rápido: si esto imprime null, tu include NO está metiendo el modal
            console.log('modal-create =>', document.getElementById('modal-create'));
            console.log('modal-edit   =>', document.getElementById('modal-edit'));
        });

        function cargarProveedores() {
            fetch(`${PROVEEDORES_BASE}/list`)
                .then(r => r.json())
                .then(data => {
                    TODOS_PROVEEDORES = data || [];
                    renderProveedores(TODOS_PROVEEDORES);
                })
                .catch(err => {
                    console.error(err);
                    if (window.Swal) Swal.fire('Error', 'No se pudo cargar el listado de proveedores.', 'error');
                    else alert('Error al cargar proveedores');
                });
        }

        function renderProveedores(lista) {
            const tbody = document.getElementById('tbody-proveedores');
            const sin   = document.getElementById('sin-proveedores');
            const lbl   = document.getElementById('total-proveedores');

            tbody.innerHTML = '';

            if (!lista.length) {
                sin.classList.remove('hidden');
                lbl.textContent = '0 registros';
                return;
            } else {
                sin.classList.add('hidden');
            }

            lista.forEach(p => {
                const badge = p.activo
                    ? '<span class="px-2 py-1 text-xs rounded bg-green-100 text-green-700">Activo</span>'
                    : '<span class="px-2 py-1 text-xs rounded bg-red-100 text-red-700">Inactivo</span>';

                const tr = document.createElement('tr');
                tr.className = 'border-b last:border-0 hover:bg-gray-50';

                tr.innerHTML = `
                    <td class="px-3 py-2">${p.nombre ?? ''}</td>
                    <td class="px-3 py-2">${p.ruc ?? ''}</td>
                    <td class="px-3 py-2">${p.contacto ?? ''}</td>
                    <td class="px-3 py-2">${p.telefono ?? ''}</td>
                    <td class="px-3 py-2">${p.email ?? ''}</td>
                    <td class="px-3 py-2 text-center">${badge}</td>
                    <td class="px-3 py-2 text-center space-x-2">
                        <button type="button" class="text-blue-600 hover:text-blue-800 text-xs"
                                onclick="openEditModal(${p.id})">Editar</button>
                        <button type="button" class="text-red-600 hover:text-red-800 text-xs"
                                onclick="eliminarProveedor(${p.id})">Eliminar</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            lbl.textContent = `${lista.length} registro(s)`;
        }

        function filtrarProveedores() {
            const q = document.getElementById('buscar-proveedor').value.toLowerCase();
            const filtrados = TODOS_PROVEEDORES.filter(p =>
                (p.nombre   || '').toLowerCase().includes(q) ||
                (p.ruc      || '').toLowerCase().includes(q) ||
                (p.contacto || '').toLowerCase().includes(q)
            );
            renderProveedores(filtrados);
        }

        function openEditModal(id) {
            const p = TODOS_PROVEEDORES.find(s => Number(s.id) === Number(id));
            if (!p) return;

            document.getElementById('e-id').value        = p.id;
            document.getElementById('e-nombre').value    = p.nombre   ?? '';
            document.getElementById('e-ruc').value       = p.ruc      ?? '';
            document.getElementById('e-telefono').value  = p.telefono ?? '';
            document.getElementById('e-email').value     = p.email    ?? '';
            document.getElementById('e-direccion').value = p.direccion ?? '';
            document.getElementById('e-contacto').value  = p.contacto ?? '';
            document.getElementById('e-activo').checked  = !!p.activo;

            const modal = document.getElementById('modal-edit');
            if (!modal) return;
            modal.classList.remove('hidden');
        }

        async function createProveedor() {
            const payload = {
                nombre:    document.getElementById('c-nombre').value.trim(),
                ruc:       document.getElementById('c-ruc').value.trim(),
                telefono:  document.getElementById('c-telefono').value.trim(),
                email:     document.getElementById('c-email').value.trim(),
                direccion: document.getElementById('c-direccion').value.trim(),
                contacto:  document.getElementById('c-contacto').value.trim(),
                activo:    document.getElementById('c-activo').checked
            };

            const resp = await fetch(PROVEEDORES_BASE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(payload)
            });

            if (!resp.ok) {
                Swal.fire('Error', 'No se pudo crear el proveedor', 'error');
                return;
            }

            Swal.fire('Éxito', 'Proveedor registrado correctamente', 'success');
            window.ProveedoresModal.closeCreate();
            cargarProveedores();
        }

        async function updateProveedor() {
            const id = document.getElementById('e-id').value;

            const payload = {
                nombre:    document.getElementById('e-nombre').value.trim(),
                ruc:       document.getElementById('e-ruc').value.trim(),
                telefono:  document.getElementById('e-telefono').value.trim(),
                email:     document.getElementById('e-email').value.trim(),
                direccion: document.getElementById('e-direccion').value.trim(),
                contacto:  document.getElementById('e-contacto').value.trim(),
                activo:    document.getElementById('e-activo').checked
            };

            const resp = await fetch(`${PROVEEDORES_BASE}/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(payload)
            });

            if (!resp.ok) {
                Swal.fire('Error', 'No se pudo actualizar', 'error');
                return;
            }

            Swal.fire('Éxito', 'Proveedor actualizado correctamente', 'success');
            window.ProveedoresModal.closeEdit();
            cargarProveedores();
        }

        async function eliminarProveedor(id) {
            const ok = await Swal.fire({
                title: '¿Eliminar proveedor?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (!ok.isConfirmed) return;

            const resp = await fetch(`${PROVEEDORES_BASE}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });

            if (!resp.ok) {
                Swal.fire('Error', 'No se pudo eliminar el proveedor', 'error');
                return;
            }

            Swal.fire('Eliminado', 'Proveedor eliminado correctamente', 'success');
            cargarProveedores();
        }
    </script>
</x-app-layout>
