{{-- ---------- Modal PERCHA --}}
<div id="modal-percha" 
     class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">

    <div class="bg-white p-6 rounded-md shadow-lg w-full max-w-lg">
        <h2 id="modal-percha-title" class="text-xl font-semibold mb-4 text-blue-900"></h2>

        <form id="form-percha">
            <input type="hidden" id="percha-id">

            <div class="mb-3">
                <label class="text-blue-800">Código</label>
                <input type="text" id="percha-codigo" class="w-full border rounded px-3 py-2">
            </div>

            <div class="mb-3">
                <label class="text-blue-800">Descripción</label>
                <textarea id="percha-descripcion" class="w-full border rounded px-3 py-2"></textarea>
            </div>

            <div class="flex justify-end space-x-2 mt-4">
                <button type="button" onclick="closePerchaModal()" 
                    class="px-4 py-2 border rounded">Cancelar</button>

                <button type="submit" 
                    class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-md shadow">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
