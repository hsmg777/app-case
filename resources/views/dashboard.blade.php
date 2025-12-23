<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">

            <!-- Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

                <!-- Facturar -->
                @hasanyrole('cashier|admin')
                <a href="{{ route('ventas.select_bodega') }}"
                   class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">
                        <!-- Icono -->
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 12h6m-6 4h6M7 4h10l2 4v12a1 1 0 01-1 1H6a1 1 0 01-1-1V4h2z" />
                        </svg>

                        <h3 class="mt-4 text-lg font-semibold text-blue-900">
                            Facturar
                        </h3>
                        <p class="text-sm text-blue-700/70 mt-1">
                            Generar ventas y comprobantes
                        </p>
                    </div>
                </a>
                @endhasanyrole

                <!-- Inventario -->
                 @hasanyrole('supervisor|admin')
                <a href="{{ route('inventario.index') }}"
                   class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M3 7h18M3 12h18M3 17h18" />
                        </svg>

                        <h3 class="mt-4 text-lg font-semibold text-blue-900">
                            Inventario
                        </h3>
                        <p class="text-sm text-blue-700/70 mt-1">
                            Control de productos y stock
                        </p>
                    </div>
                </a>
                @endhasanyrole

                <!-- Clientes -->
                 @hasanyrole('cashier|admin')
                <a href="{{ route('clients.index') }}"
                   class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m6-6a4 4 0 11-8 0 4 4 0 018 0zm6 4a4 4 0 10-8 0 4 4 0 008 0z" />
                        </svg>

                        <h3 class="mt-4 text-lg font-semibold text-blue-900">
                            Clientes
                        </h3>
                        <p class="text-sm text-blue-700/70 mt-1">
                            Gestión de clientes y datos
                        </p>
                    </div>
                </a>
                @endhasanyrole

                <!-- Proveedores -->
                 @hasanyrole('supervisor|admin')
                <a href="{{ route('proveedores.menu') }}"
                class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                        hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M16 11V7a4 4 0 00-8 0v4M5 11h14l-1 9H6l-1-9zm7 4v4m-3-4h6" />
                        </svg>

                        <h3 class="mt-4 text-lg font-semibold text-blue-900">
                            Proveedores
                        </h3>
                        <p class="text-sm text-blue-700/70 mt-1">
                            Registro y control de proveedores
                        </p>
                    </div>
                </a>
                @endhasanyrole


                <!-- Reportes ACTIVE
                @hasrole('admin')
                <a href="#"
                   class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                  d="M9 17v-6m4 6v-3m4 3V7M3 3h18v18H3V3z" />
                        </svg>

                        <h3 class="mt-4 text-lg font-semibold text-blue-900">
                            Reportes
                        </h3>
                        <p class="text-sm text-blue-700/70 mt-1">
                            Información y estadísticas
                        </p>
                    </div>
                </a>
                @endhasrole -->

                <!-- Reportes -->
                @hasrole('admin')
                <button
                    type="button"
                    id="reports-disabled-btn"
                    aria-disabled="true"
                    class="relative w-full text-left bg-white rounded-xl p-6 shadow-sm border border-blue-100
                        opacity-60 grayscale cursor-not-allowed
                        focus:outline-none focus:ring-2 focus:ring-blue-200">

                    {{-- Badge "Próximamente" --}}
                    <span class="absolute top-3 right-3 text-[11px] font-semibold px-2 py-1 rounded-full
                                bg-slate-100 text-slate-600 border border-slate-200">
                        Próximamente
                    </span>

                    <div class="flex flex-col items-center text-center">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-slate-500"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                d="M9 17v-6m4 6v-3m4 3V7M3 3h18v18H3V3z" />
                        </svg>

                        <h3 class="mt-4 text-lg font-semibold text-slate-700">
                            Reportes
                        </h3>
                        <p class="text-sm text-slate-500 mt-1">
                             Información y estadísticas
                        </p>
                    </div>
                </button>

                {{-- Placeholder / aviso --}}
                <div id="reports-disabled-toast"
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
                        <button type="button" id="reports-toast-close"
                                class="text-slate-400 hover:text-slate-700 transition">
                            ✕
                        </button>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const btn = document.getElementById('reports-disabled-btn');
                    const toast = document.getElementById('reports-disabled-toast');
                    const close = document.getElementById('reports-toast-close');

                    if (!btn || !toast) return;

                    const showToast = () => {
                        toast.classList.remove('hidden');
                        clearTimeout(window.__reportsToastTimer);
                        window.__reportsToastTimer = setTimeout(() => {
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
                @endhasrole


            </div>
        </div>
    </div>

</x-app-layout>
