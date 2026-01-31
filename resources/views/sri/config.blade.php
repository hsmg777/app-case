<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-2xl text-blue-900 leading-tight">
                {{ __('Configuración SRI (Facturación Electrónica)') }}
            </h2>

            <button onclick="history.back()"
                class="text-blue-700 hover:text-blue-900 transition flex items-center">
                <x-heroicon-s-arrow-left class="w-6 h-6 mr-1" />
                Atrás
            </button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 lg:px-6">

            <div class="bg-white rounded-2xl shadow-sm border border-blue-100 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-lg font-semibold text-blue-900">Datos del emisor</div>
                        <p class="text-sm text-slate-600">
                            La contraseña del certificado <b>NO</b> se guarda en base de datos.
                            Esta encriptada en variables de entorno para mayor seguridad.
                        </p>
                    </div>

                    <div class="text-right">
                        <div class="text-xs text-slate-500">Estado clave</div>
                        @if($envHasPassword)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                OK: configurada
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">
                                Falta: no configurada
                            </span>
                        @endif
                    </div>
                </div>

                @php
                    // ✅ Si vienes de guardar y el controller mandó ->with('clear_form', true),
                    // entonces limpiamos los campos (no mostramos config guardada ni defaults).
                    $clear = session('clear_form', false);
                @endphp

                <form id="test-cert-form" method="POST" action="{{ route('sri.config.test') }}" class="hidden">@csrf</form>

                <form method="POST" action="{{ route('sri.config.store') }}" enctype="multipart/form-data" class="mt-6">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">RUC</label>
                            <input name="ruc"
                                value="{{ old('ruc', $clear ? '' : ($config->ruc ?? '1710177245001')) }}"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="1710177245001" />
                            <p class="text-xs text-slate-500 mt-1">13 dígitos</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">Razón Social</label>
                            <input name="razon_social"
                                value="{{ old('razon_social', $clear ? '' : ($config->razon_social ?? '')) }}"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="SIMBAÑA GALARZA JOSE SALOMON" />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">Nombre Comercial</label>
                            <input name="nombre_comercial"
                                value="{{ old('nombre_comercial', $clear ? '' : ($config->nombre_comercial ?? '')) }}"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="(Opcional)" />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">Dirección Matriz</label>
                            <input name="direccion_matriz"
                                value="{{ old('direccion_matriz', $clear ? '' : ($config->direccion_matriz ?? '')) }}"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="(Opcional)" />
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">Dirección Establecimiento</label>
                            <input name="direccion_establecimiento"
                                value="{{ old('direccion_establecimiento', $clear ? '' : ($config->direccion_establecimiento ?? '')) }}"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="(Opcional)" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700">Establecimiento</label>
                                <input name="codigo_establecimiento"
                                    value="{{ old('codigo_establecimiento', $clear ? '' : ($config->codigo_establecimiento ?? '001')) }}"
                                    class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="001" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700">Punto de emisión</label>
                                <input name="codigo_punto_emision"
                                    value="{{ old('codigo_punto_emision', $clear ? '' : ($config->codigo_punto_emision ?? '001')) }}"
                                    class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="001" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">Secuencial actual</label>
                            <input type="number" min="1" name="secuencial_factura_actual"
                                value="{{ old('secuencial_factura_actual', $clear ? '' : ($config->secuencial_factura_actual ?? 1)) }}"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500" />
                            <p class="text-xs text-slate-500 mt-1">Se usará para generar 001-001-000000001…</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700">Ambiente</label>
                                @php
                                    $amb = old('ambiente', $clear ? null : ($config->ambiente ?? 1));
                                @endphp
                                <select name="ambiente"
                                    class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="1" {{ (string)$amb === '1' ? 'selected' : '' }}>1 - Pruebas</option>
                                    <option value="2" {{ (string)$amb === '2' ? 'selected' : '' }}>2 - Producción</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-slate-700">Emisión</label>
                                @php
                                    $emi = old('emision', $clear ? null : ($config->emision ?? 1));
                                @endphp
                                <select name="emision"
                                    class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                                    <option value="1" {{ (string)$emi === '1' ? 'selected' : '' }}>1 - Normal</option>
                                </select>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700">Certificado (.p12/.pfx)</label>
                            <input type="file" name="certificado_p12" accept=".p12,.pfx"
                                class="mt-1 w-full rounded-xl border border-slate-200 p-2" />

                            <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-start gap-2">
                                <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="text-xs text-blue-800">
                                    <strong class="font-semibold">Proceso automático:</strong> Tu certificado P12 será convertido 
                                    automáticamente a un formato compatible con OpenSSL 3.x y PHP (3DES). 
                                    El sistema validará el certificado y su contraseña durante el guardado.
                                </div>
                            </div>
                        </div>

                            {{-- ✅ Si se pidió limpiar el form, ocultamos el "certificado actual" --}}
                            @if(!$clear)
                                <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    @if(!empty($config?->ruta_certificado))
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex-1">
                                                <div class="text-xs font-semibold text-green-800 mb-1">✓ Certificado configurado</div>
                                                <code class="text-xs px-2 py-1 rounded bg-white border border-green-200 text-green-900">{{ basename($config->ruta_certificado) }}</code>
                                            </div>
                                            <button form="test-cert-form" type="submit" class="flex items-center gap-1.5 text-white bg-green-600 hover:bg-green-700 font-semibold px-4 py-2 rounded-lg shadow-sm transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Probar Certificado
                                            </button>
                                        </div>
                                    @else
                                        <div class="text-xs text-slate-600">
                                            ⚠️ No hay certificado guardado todavía. Por favor, sube tu archivo .p12 y su contraseña.
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700">Contraseña del Certificado</label>
                            <input type="password" name="certificado_password"
                                placeholder="Ingresa la contraseña del archivo .p12"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500" />
                            @if(!empty($config->certificado_password) || $envHasPassword)
                                <p class="text-xs text-blue-600 mt-1">
                                    * Ya existe una contraseña guardada. Déjalo en blanco si no deseas cambiarla.
                                </p>
                            @endif
                        </div>

                        <div class="md:col-span-2 flex items-center gap-3 mt-2">
                            <input id="obligado_contabilidad" type="checkbox" name="obligado_contabilidad" value="1"
                                {{ old('obligado_contabilidad', $clear ? false : ($config->obligado_contabilidad ?? false)) ? 'checked' : '' }}
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            <label for="obligado_contabilidad" class="text-sm text-slate-700">
                                Obligado a llevar contabilidad
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit"
                            class="bg-blue-700 hover:bg-blue-800 text-white px-5 py-2.5 rounded-xl font-semibold shadow-sm">
                            Guardar configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ---------- SWEETALERT NOTIFICATIONS ----------
        @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: @json(session('success')),
                timer: 4000,
                showConfirmButton: true,
                confirmButtonColor: '#1d4ed8',
            });
        @endif

        @if ($errors->any())
            let errorHtml = '<ul style="text-align:left; padding-left: 20px;">';
            @foreach ($errors->all() as $error)
                errorHtml += '<li style="margin-bottom: 8px;">{{ $error }}</li>';
            @endforeach
            errorHtml += '</ul>';

            Swal.fire({
                icon: 'error',
                title: 'Error al guardar configuración',
                html: errorHtml,
                confirmButtonColor: '#dc2626',
                customClass: {
                    popup: 'swal-wide'
                }
            });
        @endif
    </script>

    <style>
        .swal-wide {
            width: 600px !important;
        }
    </style>
</x-app-layout>
