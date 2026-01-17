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
                <!-- Facturar -->
                @hasanyrole('cashier|admin|supervisor')
                <a href="{{ auth()->user()->bodega_id ? route('cashier.open.view', ['return_to' => route('ventas.index', ['bodega' => auth()->user()->bodega_id]), 'bodega_id' => auth()->user()->bodega_id]) : route('ventas.select_bodega') }}"
                    class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">
                        <!-- Icono -->
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
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
                <a href="{{ route('inventario.index') }}" class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
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
                @hasanyrole('cashier|admin|supervisor')
                <a href="{{ route('clients.index') }}" class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
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
                <a href="{{ route('proveedores.menu') }}" class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                        hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
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



                @hasrole('admin')
                <a href="#" class="group bg-white rounded-xl p-6 shadow-sm border border-blue-100
                          hover:border-blue-400 hover:shadow-lg transition">
                    <div class="flex flex-col items-center text-center">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-blue-700 group-hover:text-blue-800 transition" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
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
                @endhasrole

            </div>
        </div>
    </div>

</x-app-layout>