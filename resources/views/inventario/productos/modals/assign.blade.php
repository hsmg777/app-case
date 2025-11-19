<div id="modal-assign" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-md p-6 rounded-lg shadow-lg border border-gray-200">

        <h2 class="text-xl font-semibold text-blue-900 mb-4">
            Asignar producto a bodega
        </h2>

        <form id="form-assign" onsubmit="submitAssign(event)">

            <input type="hidden" id="assign-producto-id">

            <div class="mb-3">
                <label class="text-sm text-gray-700">Bodega</label>
                <select id="assign-bodega" required class="w-full border rounded px-3 py-2">
                    <option value="">Seleccione...</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="text-sm text-gray-700">Percha</label>
                <select id="assign-percha" required class="w-full border rounded px-3 py-2">
                    <option value="">Seleccione...</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="text-sm text-gray-700">Stock inicial</label>
                <input type="number" min="0" id="assign-stock" required class="w-full border rounded px-3 py-2">
            </div>

            <div class="flex justify-end space-x-3 mt-4">
                <button type="button" onclick="closeAssignModal()" class="px-4 py-2 bg-gray-200 rounded">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white rounded">
                    Guardar
                </button>
            </div>
        </form>

    </div>
</div>
