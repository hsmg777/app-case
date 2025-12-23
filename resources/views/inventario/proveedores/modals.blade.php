{{-- MODAL CREAR PROVEEDOR --}}
<div id="modal-create"
     class="fixed inset-0 hidden bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">

    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-gray-100 p-6 md:p-8 animate-modal">

        {{-- Header --}}
        <div class="flex items-start justify-between mb-6">
            <div>
                <p class="text-xs font-semibold tracking-[0.15em] text-blue-400 uppercase">
                    Nuevo proveedor
                </p>
                <h2 class="text-xl md:text-2xl font-bold text-blue-900 mt-1">
                    Registrar Proveedor
                </h2>
                <p class="text-xs md:text-sm text-gray-500 mt-1">
                    Completa la información de contacto y el estado del proveedor.
                </p>
            </div>

             <button type="button"
                    onclick="ProveedoresModal.closeCreate()"
                    class="text-gray-400 hover:text-gray-600 transition">
                ✕
            </button>
        </div>

        {{-- Form --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">

            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Nombre</label>
                <input id="c-nombre" type="text"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">RUC</label>
                <input id="c-ruc" type="text"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Teléfono</label>
                <input id="c-telefono" type="text"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Email</label>
                <input id="c-email" type="email"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Dirección</label>
                <input id="c-direccion" type="text"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Nombre de Contacto</label>
                <input id="c-contacto" name="contacto" type="text"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Estado (switch) --}}
            <div class="flex items-center md:items-end md:justify-end mt-1">
                <label class="inline-flex items-center space-x-3 select-none">
                    <input id="c-activo" type="checkbox" checked
                           class="rounded-full border-gray-300 text-blue-600 focus:ring-blue-500">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-700">Proveedor activo</span>
                        <span class="text-xs text-gray-400">
                            Si lo desmarcas, quedará como inactivo.
                        </span>
                    </div>
                </label>
            </div>

        </div>

        {{-- Footer --}}
        <div class="flex justify-between items-center mt-8">

            <p class="hidden md:block text-xs text-gray-400">
                Podrás editar esta información más adelante desde el listado.
            </p>

            <div class="flex gap-3">
                <button type="button"
                        onclick="ProveedoresModal.closeCreate()"
                        class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm text-gray-700 hover:bg-gray-50 transition">
                    Cancelar
                </button>

                <button type="button"
                        onclick="createProveedor()"
                        class="px-5 py-2.5 rounded-lg text-sm font-semibold bg-blue-700 hover:bg-blue-800 text-white shadow-md shadow-blue-500/20 transition">
                    Guardar
                </button>
            </div>
        </div>

    </div>
</div>

{{-- MODAL EDITAR PROVEEDOR --}}
<div id="modal-edit"
     class="fixed inset-0 hidden bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">

    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-gray-100 p-6 md:p-8 animate-modal">

        {{-- Header --}}
        <div class="flex items-start justify-between mb-6">
            <div>
                <p class="text-xs font-semibold tracking-[0.15em] text-blue-400 uppercase">
                    Detalle proveedor
                </p>
                <h2 class="text-xl md:text-2xl font-bold text-blue-900 mt-1">
                    Editar Proveedor
                </h2>
                <p class="text-xs md:text-sm text-gray-500 mt-1">
                    Actualiza los datos de contacto o desactívalo si ya no lo utilizas.
                </p>
            </div>

            <button type="button"
                    onclick="ProveedoresModal.closeEdit()"
                    class="text-gray-400 hover:text-gray-600 transition">
                ✕
            </button>
        </div>

        <input type="hidden" id="e-id">

        {{-- Form --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">

            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Nombre</label>
                <input id="e-nombre" type="text"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">RUC</label>
                <input id="e-ruc" type="text"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Teléfono</label>
                <input id="e-telefono" type="text"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Email</label>
                <input id="e-email" type="email"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Dirección</label>
                <input id="e-direccion" type="text"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div>
                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Nombre de Contacto</label>
                <input id="e-contacto" name="contacto" type="text"
                       class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Estado (switch) --}}
            <div class="flex items-center md:items-end md:justify-end mt-1">
                <label class="inline-flex items-center space-x-3 select-none">
                    <input id="e-activo" type="checkbox"
                           class="rounded-full border-gray-300 text-blue-600 focus:ring-blue-500">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-700">Proveedor activo</span>
                        <span class="text-xs text-gray-400">
                            Desmarca para inactivar al proveedor.
                        </span>
                    </div>
                </label>
            </div>

        </div>

        {{-- Footer --}}
        <div class="flex justify-end mt-8 space-x-3">
            <button type="button"
                    onclick="ProveedoresModal.closeEdit()"
                    class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm text-gray-700 hover:bg-gray-50 transition">
                Cancelar
            </button>

            <button type="button"
                    onclick="updateProveedor()"
                    class="px-5 py-2.5 rounded-lg text-sm font-semibold bg-blue-700 hover:bg-blue-800 text-white shadow-md shadow-blue-500/20 transition">
                Guardar cambios
            </button>
        </div>

    </div>
</div>
