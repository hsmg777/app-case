<div id="cashInModal" class="hidden fixed inset-0 z-[60]">
    <div class="absolute inset-0 bg-black/40" data-close="cashInModal"></div>

    <div class="relative mx-auto mt-24 w-[92%] max-w-md bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase">Caja</p>
                <h3 class="text-lg font-bold text-slate-900">Registrar ingreso</h3>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-700 text-2xl leading-none" data-close="cashInModal">&times;</button>
        </div>

        <div class="p-5 space-y-3">
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Monto</label>
                <input id="cashInAmount" type="number" min="0.01" step="0.01"
                    class="w-full border-slate-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm h-11"
                    placeholder="Ej: 10.00">
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Motivo</label>
                <textarea id="cashInReason" rows="2"
                    class="w-full border-slate-200 rounded-xl shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                    placeholder="Ej: Ingreso por ajuste, fondo, etc."></textarea>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" data-close="cashInModal"
                    class="flex-1 px-4 py-2 rounded-xl border border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50">
                    Cancelar
                </button>
                <button type="button" id="btnSubmitCashIn"
                    class="flex-1 px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold text-sm hover:bg-blue-700">
                    Guardar ingreso
                </button>
            </div>
        </div>
    </div>
</div>
