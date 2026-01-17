<x-app-layout>

    <x-slot name="header">
        <h2 class="text-2xl font-semibold text-blue-900">Usuarios del Sistema</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto px-6">

            <!-- Botón Crear Usuario -->
            <div class="mb-6 flex justify-end">
                <button onclick="openModal('modalCrear')"
                    class="px-5 py-2 bg-blue-700 text-white rounded-lg shadow hover:bg-blue-800 transition flex items-center gap-2">
                    <x-heroicon-o-plus class="w-5 h-5" />
                    Crear usuario
                </button>
            </div>

            <!-- Tabla -->
            <div class="bg-white shadow-md rounded-xl p-6">
                <table class="w-full text-left text-blue-900">
                    <thead>
                        <tr class="border-b border-blue-100 text-blue-800">
                            <th class="py-3">Nombre</th>
                            <th class="py-3">Correo</th>
                            <th class="py-3">Rol</th>
                            <th class="py-3">Bodega</th>
                            <th class="py-3 text-center">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($usuarios as $u)
                            <tr class="border-b border-blue-100 hover:bg-blue-50 transition">
                                <td class="py-3">{{ $u->name }}</td>
                                <td class="py-3">{{ $u->email }}</td>
                                @php
                                    $roleTranslations = [
                                        'cashier' => 'Cajero',
                                        'admin' => 'Administrador',
                                    ];
                                    $roleName = $u->roles->pluck('name')->first();
                                @endphp
                                <td class="py-3 capitalize">{{ $roleTranslations[$roleName] ?? $roleName }}</td>
                                <td class="py-3">{{ $u->bodega?->nombre ?? 'N/A' }}</td>

                                <td class="py-3">
                                    <div class="flex justify-center gap-4">

                                        <!-- EDITAR (abre modal dinámico) -->
                                        <button onclick="openEditModal(@js([
                                            'id' => $u->id,
                                            'name' => $u->name,
                                            'email' => $u->email,
                                            'role_id' => $u->roles->pluck('id')->first(),
                                            'bodega_id' => $u->bodega_id
                                        ]))" class="text-blue-700 hover:text-blue-900 transition"
                                            title="Editar usuario">
                                            <x-heroicon-s-pencil-square class="w-6 h-6" />
                                        </button>

                                        <!-- ELIMINAR -->
                                        <button onclick="deleteUser('{{ $u->id }}')"
                                            class="text-red-600 hover:text-red-800 transition" title="Eliminar usuario">
                                            <x-heroicon-s-trash class="w-6 h-6" />
                                        </button>

                                        <!-- FORM HIDDEN -->
                                        <form id="delete-form-{{ $u->id }}" action="{{ route('usuarios.destroy', $u->id) }}"
                                            method="POST" class="hidden">
                                            @csrf
                                            @method('DELETE')
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>

        </div>
    </div>

    <!-- IMPORTAR MODALES -->
    @include('users.modals.create')
    @include('users.modals.edit')

    <!-- SWEETALERT NOTIFICATIONS -->
    @if(session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: '{{ session('success') }}',
                confirmButtonColor: '#2563eb',
            })
        </script>
    @endif

    @if(session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '{{ session('error') }}',
                confirmButtonColor: '#ef4444',
            })
        </script>
    @endif

    <!-- FUNCIONES DE MODALES -->
    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openEditModal(user) {
            // rellenar datos
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role_id;

            // cambiar action del form
            document.getElementById('formEditar').action = "/usuarios/" + user.id;

            openModal('modalEditar');
        }
    </script>

    <!-- CONFIRMAR ELIMINAR -->
    <script>
        function deleteUser(id) {
            Swal.fire({
                title: '¿Eliminar usuario?',
                text: "Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`delete-form-${id}`).submit();
                }
            });
        }
    </script>

</x-app-layout>