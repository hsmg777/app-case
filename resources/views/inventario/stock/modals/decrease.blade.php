<div id="modal-decrease" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">

    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">

        <h2 class="text-xl font-semibold mb-4 text-blue-900">
            Disminuir Stock
        </h2>

        <p class="text-sm mb-2 text-gray-600">
            Stock actual: <strong id="current-stock"></strong>
        </p>

        <form onsubmit="submitDecrease(event)">
            <div class="mb-3">
                <label class="text-blue-800">Cantidad</label>
                <input type="number" id="decrease-cantidad" class="w-full border rounded px-3 py-2" required min="1">
            </div>

            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeDecrease()" class="px-4 py-2 border rounded">
                    Cancelar
                </button>

                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded">
                    Guardar
                </button>
            </div>
        </form>

    </div>

</div>
