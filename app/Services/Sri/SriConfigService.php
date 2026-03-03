<?php

namespace App\Services\Sri;

use App\Models\Sri\SriConfig;
use App\Repositories\Sri\SriConfigRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
            $password = (string) ($data['certificado_password'] ?? '');

            if ($password === '') {
                throw new \Exception('La contrasena del certificado es requerida.');
            }

            $certDir = $this->certDir();
            $tempDir = $certDir . '/temp';

            // Nombre unico para el certificado.
            $originalName = 'cert_' . now()->format('Ymd_His') . '_' . Str::random(8);

            // Guardar archivo original temporalmente en disco "sri".
            $tempPath = $certFile->storeAs($tempDir, $originalName . '_original.p12', 'sri');
            $fullTempPath = Storage::disk('sri')->path($tempPath);

            if (!file_exists($fullTempPath)) {
                throw new \Exception('No se pudo guardar el archivo temporal.');
            }

            // Primero intentamos leer el archivo tal cual con la clave.
            $originalContent = file_get_contents($fullTempPath);
            $testPkcs12 = [];
            $canReadDirectly = @openssl_pkcs12_read($originalContent, $testPkcs12, $password);

            if (!$canReadDirectly) {
                Log::info('Certificado requiere conversion desde formato legacy');
            }

            // Preparar ruta final.
            $finalName = $originalName . '.p12';
            $finalPath = $certDir . '/' . $finalName;
            Storage::disk('sri')->makeDirectory($certDir);
            $fullFinalPath = Storage::disk('sri')->path($finalPath);

            // Convertir a formato compatible con PHP/OpenSSL.
            $tmpPem = $fullTempPath . '.pem';

            $cmdExtract = sprintf(
                'openssl pkcs12 -in %s -out %s -nodes -passin pass:%s 2>&1',
                escapeshellarg($fullTempPath),
                escapeshellarg($tmpPem),
                escapeshellarg($password)
            );

            exec($cmdExtract, $outputExtract, $retCodeExtract);

            if ($retCodeExtract !== 0 && file_exists('/usr/lib/ossl-modules')) {
                Log::info('Extraccion simple fallo, intentando con providers OpenSSL 3.x');
                $outputExtract = [];
                $cmdExtractLegacy = sprintf(
                    'openssl pkcs12 -provider-path /usr/lib/ossl-modules -provider legacy -provider default -in %s -out %s -nodes -passin pass:%s 2>&1',
                    escapeshellarg($fullTempPath),
                    escapeshellarg($tmpPem),
                    escapeshellarg($password)
                );
                exec($cmdExtractLegacy, $outputExtract, $retCodeExtract);
            }

            if ($retCodeExtract !== 0 || !file_exists($tmpPem)) {
                $errorMsg = implode("\n", $outputExtract);
                Log::error('Error al extraer P12 a PEM', [
                    'returnCode' => $retCodeExtract,
                    'output' => $errorMsg,
                ]);

                if (str_contains($errorMsg, 'invalid password') || str_contains($errorMsg, 'MAC verified')) {
                    throw new \Exception('Contrasena incorrecta. Verifica la contrasena del certificado P12.');
                }

                throw new \Exception('Error al leer el certificado P12. Detalle: ' . substr($errorMsg, 0, 200));
            }

            $cmdRepack = sprintf(
                'openssl pkcs12 -export -in %s -out %s -passout pass:%s 2>&1',
                escapeshellarg($tmpPem),
                escapeshellarg($fullFinalPath),
                escapeshellarg($password)
            );

            exec($cmdRepack, $outputRepack, $retCodeRepack);

            if (file_exists($tmpPem)) {
                unlink($tmpPem);
            }
            Storage::disk('sri')->delete($tempPath);

            if ($retCodeRepack !== 0) {
                $errorMsg = implode("\n", $outputRepack);
                Log::error('Error al re-empaquetar P12', [
                    'returnCode' => $retCodeRepack,
                    'output' => $errorMsg,
                ]);

                if (str_contains($errorMsg, 'Could not find private key')) {
                    throw new \Exception('No se pudo extraer la clave privada del certificado. Verifica que el P12 sea valido.');
                }

                throw new \Exception('Error al procesar el certificado P12. Detalle: ' . substr($errorMsg, 0, 200));
            }

            if (!file_exists($fullFinalPath)) {
                throw new \Exception('La conversion del certificado no genero el archivo de salida.');
            }

            $convertedContent = file_get_contents($fullFinalPath);
            $pkcs12 = [];
            if (!openssl_pkcs12_read($convertedContent, $pkcs12, $password)) {
                Storage::disk('sri')->delete($finalPath);
                throw new \Exception('El certificado convertido no es valido. Verifica la contrasena o formato del P12.');
            }

            if (!isset($pkcs12['cert']) || !isset($pkcs12['pkey'])) {
                Storage::disk('sri')->delete($finalPath);
                throw new \Exception('El certificado P12 no contiene certificado y clave privada.');
            }

            $certInfo = openssl_x509_parse($pkcs12['cert']);
            if ($certInfo) {
                $validTo = $certInfo['validTo_time_t'] ?? null;
                if ($validTo && $validTo < time()) {
                    Storage::disk('sri')->delete($finalPath);
                    $expDate = date('d/m/Y', $validTo);
                    throw new \Exception("El certificado expiro el {$expDate}. Debes renovarlo.");
                }
            }

            Log::info('Certificado P12 convertido y validado exitosamente', [
                'path' => $finalPath,
                'subject' => $certInfo['subject']['CN'] ?? 'desconocido',
                'validTo' => isset($certInfo['validTo_time_t']) ? date('Y-m-d H:i:s', $certInfo['validTo_time_t']) : 'desconocida',
            ]);

            $data['ruta_certificado'] = $finalPath;

            $oldCertPath = $this->repo->first()?->ruta_certificado ?? null;
            if ($oldCertPath && Storage::disk('sri')->exists($oldCertPath)) {
                Storage::disk('sri')->delete($oldCertPath);
            }
        } else {
            unset($data['ruta_certificado']);
        }

        if (empty($data['certificado_password'])) {
            unset($data['certificado_password']);
        }

        return $this->repo->upsert($data);
    }

    public function testCertificate(): string
    {
        $config = $this->get();

        if (!$config) {
            throw new \Exception('No existe configuracion SRI. Debes registrarla primero.');
        }

        $certPath = $config->ruta_certificado ?? null;
        if (!$certPath) {
            throw new \Exception('No hay certificado configurado.');
        }

        if (!Storage::disk('sri')->exists($certPath)) {
            throw new \Exception('El archivo del certificado no existe en el servidor.');
        }

        $password = (string) ($config->certificado_password ?? '');
        if ($password === '') {
            throw new \Exception('No se ha configurado la contrasena del certificado en BD.');
        }

        $certPath = Storage::disk('sri')->path($config->ruta_certificado);
        $certContent = file_get_contents($certPath);

        $certs = [];
        if (!openssl_pkcs12_read($certContent, $certs, $password)) {
            throw new \Exception('No se pudo leer el certificado P12. Verifica la contrasena o el formato del archivo.');
        }

        if (!isset($certs['cert']) || !isset($certs['pkey'])) {
            throw new \Exception('El certificado P12 no contiene certificado y clave privada.');
        }

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

        return "Certificado valido. Propietario: {$subject}. Valido hasta: {$expirationDate}";
    }

    public function getOrFailForUpdate(): \App\Models\Sri\SriConfig
    {
        $cfg = \App\Models\Sri\SriConfig::query()->lockForUpdate()->first();

        if (!$cfg) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sri' => 'No existe configuracion SRI. Debes registrarla primero en el panel.',
            ]);
        }

        return $cfg;
    }

    private function certDir(): string
    {
        $dir = trim((string) env('SRI_CERT_DIR', 'sri/certs'));
        $dir = trim($dir, '/');

        return $dir === '' ? 'sri/certs' : $dir;
    }
}
