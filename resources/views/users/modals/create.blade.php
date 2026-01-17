<!-- Modal Crear Usuario -->
<div id="modalCrear" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
    <div class="bg-white w-full max-w-md rounded-xl shadow-lg p-6">

        <h2 class="text-xl font-semibold text-blue-900 mb-4 flex items-center gap-2">
            <x-heroicon-o-plus class="w-6 h-6" />
            Crear Usuario
        </h2>

        <form action="{{ route('usuarios.store') }}" method="POST">
            @csrf

            <!-- Nombre -->
            <div class="mb-4">
                <label class="block mb-1 font-medium">Nombre completo</label>
                <input type="text" name="name" class="w-full rounded-lg border-gray-300 focus:ring-blue-600" required>
            </div>

            <!-- Email -->
            <div class="mb-4">
                <label class="block mb-1 font-medium">Correo</label>
                <input type="email" name="email" class="w-full rounded-lg border-gray-300 focus:ring-blue-600" required>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label class="block mb-1 font-medium">Contraseña</label>
                <input type="password" name="password" class="w-full rounded-lg border-gray-300 focus:ring-blue-600"
                    required>
            </div>

            <!-- Rol -->
            <div class="mb-4">
                <label class="block mb-1 font-medium">Rol</label>
                <select name="role_id" class="w-full rounded-lg border-gray-300 focus:ring-blue-600" required>
                    <option value="">Seleccione</option>
                    @php
                        $roleTranslations = ['cashier' => 'Cajero', 'admin' => 'Administrador', 'supervisor' => 'Supervisor'];
                    @endphp
                    @foreach($roles as $id => $name)
                        <option value="{{ $id }}">{{ $roleTranslations[$name] ?? ucfirst($name) }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Bodega (Opcional, pero recomendada) -->
            <div class="mb-4">
                <label class="block mb-1 font-medium">Bodega Asignada</label>
                <select name="bodega_id" class="w-full rounded-lg border-gray-300 focus:ring-blue-600">
                    <option value="">Ninguna</option>
                    @foreach($bodegas as $id => $nombre)
                        <option value="{{ $id }}">{{ $nombre }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Si se asigna, el usuario facturará automáticamente desde esta
                    bodega.</p>
            </div>

            <!-- Botones -->
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal('modalCrear')"
                    class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300">
                    Cancelar
                </button>

                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-700 text-white hover:bg-blue-800">
                    Guardar
                </button>
            </div>

        </form>
    </div>
</div>