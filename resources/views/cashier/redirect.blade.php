<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-2xl text-slate-900 leading-tight">
            Guardando cierre...
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-xl mx-auto px-6">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 text-center">
                <p class="text-slate-700 text-sm">
                    Procesando, por favor espera...
                </p>
            </div>
        </div>
    </div>

    {{-- Si SweetAlert2 ya está cargado globalmente, puedes quitar este CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            Swal.fire({
                icon: 'success',
                title: 'Guardado exitoso',
                text: @json($message ?? 'Cierre de caja guardado.'),
                confirmButtonText: 'Continuar',
                timer: 1400,
                timerProgressBar: true
            }).then(() => {
                window.location.href = @json($redirectTo);
            });
        });
    </script>
</x-app-layout>
