<x-app-layout>

    <x-slot name="header">
        <div class="flex justify-between items-center">

            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Bodegas y Perchas') }}
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

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- ================================
                        PANEL IZQUIERDO: BODEGAS
                ================================= --}}
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-blue-800">Bodegas</h3>
                        <button 
                            onclick="openCreateBodegaModal()" 
                            class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-md shadow">
                            + Nueva Bodega
                        </button>
                    </div>

                    <table class="min-w-full text-sm">
                        <thead class="border-b bg-blue-50">
                            <tr>
                                <th class="py-2 px-3 text-left">Nombre</th>
                                <th class="py-2 px-3 text-left">Tipo</th>
                                <th class="py-2 px-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-bodegas"></tbody>
                    </table>
                </div>

                {{-- ================================
                        PANEL DERECHO: PERCHAS
                ================================= --}}
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">

                    <div class="flex justify-between items-center mb-4">
                        <h3 id="titulo-perchas" class="text-xl font-semibold text-blue-800">
                            Seleccione una bodega
                        </h3>

                        <button 
                            onclick="openCreatePerchaModal()"
                            id="btn-nueva-percha"
                            class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-md shadow hidden">
                            + Nueva Percha
                        </button>
                    </div>

                    <table class="min-w-full text-sm">
                        <thead class="border-b bg-blue-50">
                            <tr>
                                <th class="py-2 px-3 text-left">Código</th>
                                <th class="py-2 px-3 text-left">Descripción</th>
                                <th class="py-2 px-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-perchas"></tbody>
                    </table>

                </div>

            </div>

        </div>
    </div>

    @include('inventario.bodegas_perchas.modals.modal_bodega')
    @include('inventario.bodegas_perchas.modals.modal_percha')
    
    <script>
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
        let bodegaSeleccionada = null;

        document.addEventListener('DOMContentLoaded', function () {
            cargarBodegas();
        });

        /* -------------------------------------------------------------
                        HELPERS
        ------------------------------------------------------------- */
        function fetchJson(url, options = {}) {
            options.headers = {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF_TOKEN,
                ...(options.headers || {})
            };
            return fetch(url, options).then(res => res.json());
        }

        /* -------------------------------------------------------------
                            CARGAR BODEGAS
        ------------------------------------------------------------- */
        function cargarBodegas() {
            fetchJson('/inventario/bodegas')
                .then(data => {
                    let html = '';
                    data.forEach(b => {
                        html += `
                            <tr class="border-b hover:bg-gray-50 cursor-pointer"
                                onclick="seleccionarBodega(${b.id}, '${b.nombre}')">

                                <td class="py-2 px-3">${b.nombre}</td>
                                <td class="py-2 px-3">${b.tipo}</td>

                                <td class="py-2 px-3 text-center">
                                    <button onclick="editarBodega(event, ${b.id})" 
                                            class="text-blue-700 hover:underline mr-3">Editar</button>

                                    <button onclick="eliminarBodega(event, ${b.id})" 
                                            class="text-red-600 hover:underline">Eliminar</button>
                                </td>
                            </tr>
                        `;
                    });
                    document.getElementById('tabla-bodegas').innerHTML = html;
                });
        }

        function seleccionarBodega(id, nombre) {
            bodegaSeleccionada = id;
            document.getElementById('titulo-perchas').innerText = "Perchas de " + nombre;
            document.getElementById('btn-nueva-percha').classList.remove('hidden');
            cargarPerchas(id);
        }

        /* -------------------------------------------------------------
                            MODALES BODEGA
        ------------------------------------------------------------- */
        function openCreateBodegaModal() {
            document.getElementById('modal-bodega-title').innerText = 'Nueva Bodega';
            document.getElementById('modal-bodega').classList.remove('hidden');
            document.getElementById('bodega-id').value = '';
            document.getElementById('form-bodega').reset();
        }

        function closeBodegaModal() {
            document.getElementById('modal-bodega').classList.add('hidden');
        }

        function editarBodega(e, id) {
            e.stopPropagation();

            fetchJson(`/inventario/bodegas/${id}`)
                .then(b => {
                    document.getElementById('modal-bodega-title').innerText = 'Editar Bodega';
                    document.getElementById('modal-bodega').classList.remove('hidden');

                    document.getElementById('bodega-id').value = b.id;
                    document.getElementById('bodega-nombre').value = b.nombre;
                    document.getElementById('bodega-tipo').value = b.tipo;
                    document.getElementById('bodega-ubicacion').value = b.ubicacion ?? '';
                    document.getElementById('bodega-descripcion').value = b.descripcion ?? '';
                });
        }

        document.getElementById('form-bodega').addEventListener('submit', function (e) {
            e.preventDefault();

            let id = document.getElementById('bodega-id').value;

            let payload = {
                nombre: document.getElementById('bodega-nombre').value,
                tipo: document.getElementById('bodega-tipo').value,
                ubicacion: document.getElementById('bodega-ubicacion').value,
                descripcion: document.getElementById('bodega-descripcion').value,
            };

            let method = id ? 'PUT' : 'POST';
            let url = id ? `/inventario/bodegas/${id}` : '/inventario/bodegas';

            fetchJson(url, {
                method: method,
                body: JSON.stringify(payload)
            })
            .then(() => {
                Swal.fire('Éxito', 'Bodega guardada correctamente', 'success');
                closeBodegaModal();
                cargarBodegas();
            });
        });

        function eliminarBodega(e, id) {
            e.stopPropagation();

            Swal.fire({
                title: '¿Eliminar bodega?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
            }).then(r => {
                if (r.isConfirmed) {
                    fetchJson(`/inventario/bodegas/${id}`, { method: 'DELETE' })
                        .then(() => {
                            Swal.fire('Eliminado', 'Bodega eliminada', 'success');
                            cargarBodegas();
                        });
                }
            });
        }

        /* -------------------------------------------------------------
                            PERCHAS
        ------------------------------------------------------------- */
        function cargarPerchas(bodegaId) {
            fetchJson(`/inventario/perchas/bodega/${bodegaId}`)
                .then(data => {
                    let html = '';
                    data.forEach(p => {
                        html += `
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-3">${p.codigo}</td>
                                <td class="py-2 px-3">${p.descripcion ?? ''}</td>

                                <td class="py-2 px-3 text-center">
                                    <button onclick="editarPercha(${p.id})" 
                                            class="text-blue-700 hover:underline mr-3">Editar</button>

                                    <button onclick="eliminarPercha(${p.id})" 
                                            class="text-red-600 hover:underline">Eliminar</button>
                                </td>
                            </tr>
                        `;
                    });
                    document.getElementById('tabla-perchas').innerHTML = html;
                });
        }

        /* ------- MODALES PERCHA ------- */

        function openCreatePerchaModal() {
            if (!bodegaSeleccionada) return;

            document.getElementById('modal-percha-title').innerText = 'Nueva Percha';
            document.getElementById('modal-percha').classList.remove('hidden');
            document.getElementById('form-percha').reset();
            document.getElementById('percha-id').value = '';
        }

        function closePerchaModal() {
            document.getElementById('modal-percha').classList.add('hidden');
        }

        function editarPercha(id) {
            fetchJson(`/inventario/perchas/${id}`)
                .then(p => {
                    document.getElementById('modal-percha-title').innerText = 'Editar Percha';
                    document.getElementById('modal-percha').classList.remove('hidden');

                    document.getElementById('percha-id').value = p.id;
                    document.getElementById('percha-codigo').value = p.codigo;
                    document.getElementById('percha-descripcion').value = p.descripcion ?? '';
                });
        }

        document.getElementById('form-percha').addEventListener('submit', function (e) {
            e.preventDefault();

            let id = document.getElementById('percha-id').value;

            let payload = {
                bodega_id: bodegaSeleccionada,
                codigo: document.getElementById('percha-codigo').value,
                descripcion: document.getElementById('percha-descripcion').value,
            };

            let method = id ? 'PUT' : 'POST';
            let url = id ? `/inventario/perchas/${id}` : '/inventario/perchas';

            fetchJson(url, {
                method: method,
                body: JSON.stringify(payload)
            })
            .then(() => {
                Swal.fire('Éxito', 'Percha guardada', 'success');
                closePerchaModal();
                cargarPerchas(bodegaSeleccionada);
            });
        });

        function eliminarPercha(id) {
            Swal.fire({
                title: '¿Eliminar percha?',
                text: 'No se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
            }).then(r => {
                if (r.isConfirmed) {
                    fetchJson(`/inventario/perchas/${id}`, { method: 'DELETE' })
                        .then(() => {
                            Swal.fire('Eliminado', 'Percha eliminada', 'success');
                            cargarPerchas(bodegaSeleccionada);
                        });
                }
            });
        }

    </script>


</x-app-layout>
