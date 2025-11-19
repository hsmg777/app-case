<div id="modal-increase" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center">

    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">

        <h2 class="text-xl font-semibold mb-4 text-blue-900">
            Aumentar Stock
        </h2>

        <form onsubmit="submitIncrease(event)">
            <div class="mb-3">
                <label class="text-blue-800">Cantidad</label>
                <input type="number" id="increase-cantidad" class="w-full border rounded px-3 py-2" required min="1">
            </div>

            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeIncrease()" class="px-4 py-2 border rounded">
                    Cancelar
                </button>

                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">
                    Guardar
                </button>
            </div>
        </form>

    </div>

</div>
