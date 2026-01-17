<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">

            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Inventario') }}
            </h2>

            <button onclick="window.location.href='{{ route('dashboard') }}'"
                class="text-blue-700 hover:text-blue-900 transition flex items-center space-x-1" title="Regresar">
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
                <a href="{{ route('productos.index') }}" class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
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
                <a href="{{ route('inventario.stock') }}" class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
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



                <a href="{{ route('inventario.bodegas_perchas') }}" class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                        hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
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
                </a>
            </div>
        </div>
    </div>
</x-app-layout>