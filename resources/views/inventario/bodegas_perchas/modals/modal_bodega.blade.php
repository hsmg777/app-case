{{-- ---------- Modal BODEGA --}}
<div id="modal-bodega" 
     class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">

    <div class="bg-white p-6 rounded-md shadow-lg w-full max-w-lg">
        <h2 id="modal-bodega-title" class="text-xl font-semibold mb-4 text-blue-900"></h2>

        <form id="form-bodega">
            <input type="hidden" id="bodega-id">

            <div class="mb-3">
                <label class="text-blue-800">Nombre</label>
                <input type="text" id="bodega-nombre" class="w-full border rounded px-3 py-2">
            </div>

            <div class="mb-3">
                <label class="text-blue-800">Tipo</label>
                <input type="text" id="bodega-tipo" class="w-full border rounded px-3 py-2">
            </div>

            <div class="mb-3">
                <label class="text-blue-800">Ubicación</label>
                <input type="text" id="bodega-ubicacion" class="w-full border rounded px-3 py-2">
            </div>

            <div class="mb-3">
                <label class="text-blue-800">Descripción</label>
                <textarea id="bodega-descripcion" class="w-full border rounded px-3 py-2"></textarea>
            </div>

            <div class="flex justify-end space-x-2 mt-4">
                <button type="button" onclick="closeBodegaModal()" 
                    class="px-4 py-2 border rounded">Cancelar</button>

                <button type="submit" 
                    class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-md shadow">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
