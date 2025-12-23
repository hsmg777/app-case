<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-slate-900 leading-tight">
                Selecciona la bodega
            </h2>

            <button
                onclick="window.location.href='{{ route('dashboard') }}'"
                class="text-slate-600 hover:text-slate-900 transition flex items-center space-x-1 text-sm"
            >
                <x-heroicon-s-arrow-left class="w-5 h-5" />
                <span>Atrás</span>
            </button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($bodegas as $bodega)
                    <a
                        href="{{ route('cashier.open.view', ['return_to' => route('ventas.index', $bodega->id), 'bodega_id' => $bodega->id]) }}"
                        class="group bg-white rounded-2xl p-6 border border-slate-200 shadow-sm
                               hover:shadow-lg hover:border-blue-400 transition"
                    >
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                            Bodega
                        </p>
                        <h3 class="mt-1 text-lg font-bold text-slate-900 group-hover:text-blue-700 transition">
                            {{ $bodega->nombre }}
                        </h3>

                        <p class="mt-2 text-sm text-slate-500">
                            Click para facturar desde esta bodega.
                        </p>

                        <div class="mt-4 inline-flex items-center text-sm font-semibold text-blue-700">
                            Continuar →
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
