<x-app-layout>
     <x-slot name="header">
        <div class="flex justify-between items-center">

            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Inventario') }}
            </h2>

            <button 
                onclick="window.location.href='{{ route('dashboard') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center space-x-1"
                title="Regresar"
            >   
                <x-heroicon-s-arrow-left class="w-5 h-5" />
                <span>Atrás</span>
            </button>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">

            <!-- Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- Productos -->
                <a href="{{ route('productos.index') }}"
                   class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3 7l1.664 9.152A2 2 0 006.64 18h10.72a2 2 0 001.976-1.848L21 7H3z" />
                        </svg>

                        <h3 class="mt-4 text-lg font-semibold text-blue-900">
                            Productos y precios
                        </h3>
                        <p class="text-sm text-blue-700/70 mt-1">
                            Gestión de productos, códigos y precios
                        </p>
                    </div>
                </a>

                <!-- Inventario -->
                <a href="{{ route('inventario.stock') }}"
                   class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M4 4h16v16H4V4z" />
                        </svg>

                        <h3 class="mt-4 text-lg font-semibold text-blue-900">
                            Stock
                        </h3>
                        <p class="text-sm text-blue-700/70 mt-1">
                            Control total de inventario
                        </p>
                    </div>
                </a>
                <!-- Bodegas (DESACTIVADO - Fase actual) -->
                <button
                    type="button"
                    id="bodegas-disabled-btn"
                    aria-disabled="true"
                    class="relative w-full text-left bg-white rounded-xl p-6 shadow-sm border border-blue-100
                        opacity-60 grayscale cursor-not-allowed
                        focus:outline-none focus:ring-2 focus:ring-blue-200">

                    <span class="absolute top-3 right-3 text-[11px] font-semibold px-2 py-1 rounded-full
                                bg-slate-100 text-slate-600 border border-slate-200">
                        Próximamente
                    </span>

                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-slate-500"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3 10l9-7 9 7v10a2 2 0 01-2 2H5a2 2 0 01-2-2V10z" />
                        </svg>

                        <h3 class="mt-4 text-lg font-semibold text-slate-700">
                            Bodegas y perchas
                        </h3>
                        <p class="text-sm text-slate-500 mt-1">
                            Este módulo no está activo en la fase actual
                        </p>
                    </div>
                </button>


                <!-- Bodegas ACTIVADO
                <a href="{{ route('inventario.bodegas_perchas') }}"
                        class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                        hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M3 10l9-7 9 7v10a2 2 0 01-2 2H5a2 2 0 01-2-2V10z" />
                        </svg>

                        <h3 class="mt-4 text-lg font-semibold text-blue-900">
                            Bodegas y perchas
                        </h3>
                        <p class="text-sm text-blue-700/70 mt-1">
                            Gestión de bodegas y perchas
                        </p>
                    </div>
                </a>-->
            </div>
        </div>
    </div>

    {{-- Placeholder / aviso (toast) --}}
    <div id="bodegas-disabled-toast"
        class="hidden fixed bottom-6 right-6 z-50 max-w-sm bg-white border border-slate-200 shadow-lg rounded-xl p-4">
        <div class="flex items-start gap-3">
            <div class="mt-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-700" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                        d="M13 16h-1v-4h-1m1-4h.01M12 19a7 7 0 100-14 7 7 0 000 14z" />
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-slate-900">Módulo no disponible</p>
                <p class="text-sm text-slate-600 mt-0.5">
                    Este módulo no está activo en la fase actual del sistema.
                </p>
            </div>
            <button type="button" id="bodegas-toast-close"
                    class="text-slate-400 hover:text-slate-700 transition">
                ✕
            </button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('bodegas-disabled-btn');
        const toast = document.getElementById('bodegas-disabled-toast');
        const close = document.getElementById('bodegas-toast-close');

        if (!btn || !toast) return;

        const showToast = () => {
            toast.classList.remove('hidden');
            clearTimeout(window.__bodegasToastTimer);
            window.__bodegasToastTimer = setTimeout(() => {
                toast.classList.add('hidden');
            }, 3500);
        };

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            showToast();
        });

        if (close) {
            close.addEventListener('click', () => toast.classList.add('hidden'));
        }
    });
    </script>

</x-app-layout>
