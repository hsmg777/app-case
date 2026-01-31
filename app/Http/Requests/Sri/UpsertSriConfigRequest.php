<?php

namespace App\Http\Requests\Sri;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSriConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ajusta con policy/roles si quieres
    }

    public function rules(): array
    {
        return [
            'ruc' => ['required', 'digits:13'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],

            'direccion_matriz' => ['nullable', 'string', 'max:255'],
            'direccion_establecimiento' => ['nullable', 'string', 'max:255'],

            'codigo_establecimiento' => ['required', 'digits:3'],
            'codigo_punto_emision' => ['required', 'digits:3'],

            'secuencial_factura_actual' => ['required', 'integer', 'min:1'],

            'ambiente' => ['required', 'in:1,2'], // 1 pruebas, 2 producción
            'emision' => ['required', 'in:1'],

            'obligado_contabilidad' => ['nullable', 'boolean'],

            // Certificado (opcional si ya existe uno guardado)
            // Certificado (opcional si ya existe uno guardado)
            'certificado_p12' => ['nullable', 'file', 'mimes:p12,pfx,bin', 'max:5120'], // 5MB
            'certificado_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Para que checkbox funcione (si no viene, sea false)
        $this->merge([
            'obligado_contabilidad' => $this->boolean('obligado_contabilidad'),
        ]);
    }
}
