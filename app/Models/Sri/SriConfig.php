<?php

namespace App\Models\Sri;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SriConfig extends Model
{
    use HasFactory;

    protected $table = 'sri_configs';

    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion_matriz',
        'direccion_establecimiento',
        'codigo_establecimiento',
        'codigo_punto_emision',
        'secuencial_factura_actual',
        'ambiente',
        'emision',
        'ruta_certificado',
        'certificado_password',
        'obligado_contabilidad',
    ];

    protected $hidden = [
        'certificado_password',
    ];

    protected $casts = [
        'secuencial_factura_actual' => 'integer',
        'obligado_contabilidad' => 'boolean',
    ];

    /**
     *  P12 .env
     */
    public function getCertPasswordAttribute(): ?string
    {
        if (!empty($this->certificado_password)) {
            return $this->certificado_password;
        }
        return env('SRI_CERT_PASSWORD');
    }

    /**
     * Path absoluto del certificado en storage/app/sri/certs/
     */
    public function getCertAbsolutePathAttribute(): ?string
    {
        if (!$this->ruta_certificado)
            return null;
        return \Illuminate\Support\Facades\Storage::disk('sri')->path($this->ruta_certificado);
    }
}
