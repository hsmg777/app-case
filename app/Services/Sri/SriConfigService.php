<?php

namespace App\Services\Sri;

use App\Models\Sri\SriConfig;
use App\Repositories\Sri\SriConfigRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SriConfigService
{
    public function __construct(private SriConfigRepository $repo)
    {
    }

    public function get(): ?SriConfig
    {
        return $this->repo->first();
    }

    public function save(array $data, ?UploadedFile $certFile): SriConfig
    {
        if ($certFile) {
            $password = $data['certificado_password'] ?? env('SRI_CERT_PASSWORD');

            if (!$password) {
                throw new \Exception('⚠️ La contraseña del certificado es requerida.');
            }

            // Nombre único para el certificado
            $originalName = 'cert_' . now()->format('Ymd_His') . '_' . Str::random(8);

            // Guardar archivo original temporalmente - USANDO DISCO 'sri'
            $tempPath = $certFile->storeAs('sri/certs/temp', $originalName . '_original.p12', 'sri');
            $fullTempPath = Storage::disk('sri')->path($tempPath);

            if (!file_exists($fullTempPath)) {
                throw new \Exception("❌ No se pudo guardar el archivo temporal.");
            }

            // PRIMERO: Validar que podemos leer el archivo original con la contraseña
            $originalContent = file_get_contents($fullTempPath);
            $testPkcs12 = [];

            // Intentar leer directamente primero (puede funcionar si ya es formato moderno)
            $canReadDirectly = @openssl_pkcs12_read($originalContent, $testPkcs12, $password);

            if (!$canReadDirectly) {
                // Si no se puede leer directamente, probablemente es formato legacy
                // Necesitamos hacer la conversión con providers
                \Log::info('Certificado requiere conversión desde formato legacy');
            }

            // Preparar ruta final
            $finalName = $originalName . '.p12';
            $finalPath = 'sri/certs/' . $finalName;
            Storage::disk('sri')->makeDirectory('sri/certs');
            $fullFinalPath = Storage::disk('sri')->path($finalPath);

            // Convertir a formato compatible con PHP
            // Estrategia: Extraer a PEM y re-empaquetar (más confiable que export directo)
            $tmpPem = $fullTempPath . '.pem';

            // Paso 1: Extraer a PEM sin cifrado
            $cmdExtract = sprintf(
                'openssl pkcs12 -in %s -out %s -nodes -passin pass:%s 2>&1',
                escapeshellarg($fullTempPath),
                escapeshellarg($tmpPem),
                escapeshellarg($password)
            );

            exec($cmdExtract, $outputExtract, $retCodeExtract);

            // Paso 2: Si la extracción simple falla, intentar con providers para OpenSSL 3.x
            if ($retCodeExtract !== 0 && file_exists('/usr/lib/ossl-modules')) {
                \Log::info('Extracción simple falló, intentando con providers OpenSSL 3.x');
                $outputExtract = [];
                $cmdExtractLegacy = sprintf(
                    'openssl pkcs12 -provider-path /usr/lib/ossl-modules -provider legacy -provider default -in %s -out %s -nodes -passin pass:%s 2>&1',
                    escapeshellarg($fullTempPath),
                    escapeshellarg($tmpPem),
                    escapeshellarg($password)
                );
                exec($cmdExtractLegacy, $outputExtract, $retCodeExtract);
            }

            // Verificar que la extracción fue exitosa
            if ($retCodeExtract !== 0 || !file_exists($tmpPem)) {
                $errorMsg = implode("\n", $outputExtract);
                \Log::error('Error al extraer P12 a PEM', [
                    'returnCode' => $retCodeExtract,
                    'output' => $errorMsg,
                ]);

                if (str_contains($errorMsg, 'invalid password') || str_contains($errorMsg, 'MAC verified')) {
                    throw new \Exception("🔒 Contraseña incorrecta. Por favor verifica la contraseña del certificado P12.");
                } else {
                    throw new \Exception("❌ Error al leer el certificado P12. Detalle: " . substr($errorMsg, 0, 200));
                }
            }

            // Paso 3: Re-empaquetar en formato P12 compatible con PHP
            $cmdRepack = sprintf(
                'openssl pkcs12 -export -in %s -out %s -passout pass:%s 2>&1',
                escapeshellarg($tmpPem),
                escapeshellarg($fullFinalPath),
                escapeshellarg($password)
            );

            exec($cmdRepack, $outputRepack, $retCodeRepack);

            // Limpiar archivos temporales
            if (file_exists($tmpPem)) {
                unlink($tmpPem);
            }
            Storage::disk('sri')->delete($tempPath);

            // Verificar que el re-empaquetado fue exitoso
            if ($retCodeRepack !== 0) {
                $errorMsg = implode("\n", $outputRepack);
                \Log::error('Error al re-empaquetar P12', [
                    'returnCode' => $retCodeRepack,
                    'output' => $errorMsg,
                ]);

                if (str_contains($errorMsg, 'Could not find private key')) {
                    throw new \Exception("🔑 No se pudo extraer la clave privada del certificado. Verifica que el archivo P12 sea válido y contenga la clave privada.");
                } else {
                    throw new \Exception("❌ Error al procesar el certificado P12. Detalle: " . substr($errorMsg, 0, 200));
                }
            }

            if (!file_exists($fullFinalPath)) {
                throw new \Exception("❌ La conversión del certificado no generó el archivo de salida.");
            }

            // Validar que el certificado convertido sea legible
            $convertedContent = file_get_contents($fullFinalPath);
            $pkcs12 = [];
            if (!openssl_pkcs12_read($convertedContent, $pkcs12, $password)) {
                Storage::disk('sri')->delete($finalPath);
                throw new \Exception('❌ El certificado convertido no es válido. Verifica la contraseña o el formato del archivo P12.');
            }

            // Validar que contenga los componentes necesarios
            if (!isset($pkcs12['cert']) || !isset($pkcs12['pkey'])) {
                Storage::disk('sri')->delete($finalPath);
                throw new \Exception('❌ El certificado P12 no contiene los componentes necesarios (certificado o clave privada).');
            }

            // Verificar fecha de expiración
            $certInfo = openssl_x509_parse($pkcs12['cert']);
            if ($certInfo) {
                $validTo = $certInfo['validTo_time_t'] ?? null;
                if ($validTo && $validTo < time()) {
                    Storage::disk('sri')->delete($finalPath);
                    $expDate = date('d/m/Y', $validTo);
                    throw new \Exception("⏰ El certificado ha expirado el {$expDate}. Debes renovarlo con el SRI.");
                }
            }

            \Log::info('Certificado P12 convertido y validado exitosamente', [
                'path' => $finalPath,
                'subject' => $certInfo['subject']['CN'] ?? 'desconocido',
                'validTo' => isset($certInfo['validTo_time_t']) ? date('Y-m-d H:i:s', $certInfo['validTo_time_t']) : 'desconocida',
            ]);

            $data['ruta_certificado'] = $finalPath;

            // Eliminar certificado anterior si existe
            $oldCertPath = $this->repo->first()?->ruta_certificado ?? null;
            if ($oldCertPath && Storage::disk('sri')->exists($oldCertPath)) {
                Storage::disk('sri')->delete($oldCertPath);
            }
        } else {
            // Si no suben archivo, no actualizamos la ruta
            unset($data['ruta_certificado']);
        }

        // Si el password viene vacío, no lo actualizamos
        if (empty($data['certificado_password'])) {
            unset($data['certificado_password']);
        }

        return $this->repo->upsert($data);
    }

    public function testCertificate(): string
    {
        $config = $this->get();

        if (!$config) {
            throw new \Exception('No existe configuración SRI. Debes registrarla primero.');
        }

        $certPath = $config->ruta_certificado ?? null;
        if (!$certPath) {
            throw new \Exception('No hay certificado configurado.');
        }

        if (!Storage::disk('sri')->exists($certPath)) {
            throw new \Exception('El archivo del certificado no existe en el servidor.');
        }

        $password = $config->certificado_password ?? env('SRI_CERT_PASSWORD');

        if (!$password) {
            throw new \Exception('No se ha configurado la contraseña del certificado.');
        }

        $certPath = Storage::disk('sri')->path($config->ruta_certificado);
        $certContent = file_get_contents($certPath);

        $certs = [];
        if (!openssl_pkcs12_read($certContent, $certs, $password)) {
            throw new \Exception('No se pudo leer el certificado P12. Verifica la contraseña o el formato del archivo.');
        }

        // Verificar que tenga los componentes necesarios
        if (!isset($certs['cert']) || !isset($certs['pkey'])) {
            throw new \Exception('El certificado P12 no contiene los componentes necesarios (certificado o clave privada).');
        }

        // Verificar fecha de expiración
        $certInfo = openssl_x509_parse($certs['cert']);
        if (!$certInfo) {
            throw new \Exception('No se pudo parsear el certificado.');
        }

        $validTo = $certInfo['validTo_time_t'] ?? null;
        if ($validTo && $validTo < time()) {
            throw new \Exception('El certificado ha expirado.');
        }

        $expirationDate = $validTo ? date('Y-m-d H:i:s', $validTo) : 'desconocida';
        $subject = $certInfo['subject']['CN'] ?? 'desconocido';

        return "✅ Certificado válido. Propietario: {$subject}. Válido hasta: {$expirationDate}";
    }

    public function getOrFailForUpdate(): \App\Models\Sri\SriConfig
    {
        $cfg = \App\Models\Sri\SriConfig::query()->lockForUpdate()->first();

        if (!$cfg) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sri' => 'No existe configuración SRI. Debes registrarla primero en el panel.',
            ]);
        }

        return $cfg;
    }

}
