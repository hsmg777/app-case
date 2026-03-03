<?php

namespace App\Services\Sri;

use App\Models\Sales\Sale;
use App\Models\Sri\SriConfig;
use App\Repositories\Sri\ElectronicInvoiceRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use SoapClient;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use App\Models\Sri\ElectronicInvoice;



class SriInvoiceService
{
    public function __construct(
        private SriConfigService $configService,
        private ElectronicInvoiceRepository $repo
    ) {
    }

    private function documentsDisk(): string
    {
        return (string) config('sri.documents_disk', 'local');
    }

    private function storageContext(string $disk, string $path): array
    {
        $diskConfig = (array) config("filesystems.disks.{$disk}", []);
        return [
            'disk' => $disk,
            'driver' => $diskConfig['driver'] ?? null,
            'bucket' => $diskConfig['bucket'] ?? null,
            'region' => $diskConfig['region'] ?? null,
            'visibility' => $diskConfig['visibility'] ?? null,
            'directory_visibility' => $diskConfig['directory_visibility'] ?? null,
            'path' => $path,
        ];
    }

    private function putDocument(string $path, string $contents, string $stage): void
    {
        $disk = $this->documentsDisk();
        $base = $this->storageContext($disk, $path);
        $base['stage'] = $stage;
        $base['bytes'] = strlen($contents);

        Log::info('SRI storage write start', $base);

        try {
            $ok = Storage::disk($disk)->put($path, $contents);
            if ($ok !== true) {
                throw new RuntimeException('Storage::put returned false');
            }
            Log::info('SRI storage write success', $base);
        } catch (Throwable $e) {
            $base['error'] = $e->getMessage();
            Log::error('SRI storage write failed', $base);
            throw $e;
        }
    }

    public function isProductionEnv(): bool
    {
        $cfg = $this->configService->get();
        return (int) ($cfg->ambiente ?? 1) === 2;
    }

    public function getInvoiceBySaleId(int $saleId): ?ElectronicInvoice
    {
        return $this->repo->findBySaleId($saleId);
    }

    public function markInvoiceError(int $saleId, string $message): void
    {
        $inv = $this->repo->findBySaleId($saleId);
        if (!$inv)
            return;

        $inv->mensaje_error = mb_substr($message, 0, 2000);
        // Opcional: para que se vea como pendiente de reintento en UI
        if (!in_array(strtoupper((string) $inv->estado_sri), ['AUTORIZADO', 'RECHAZADO'], true)) {
            $inv->estado_sri = 'PENDIENTE_REINTENTO';
        }
        $inv->save();
    }


    public function generateXmlForSale(int $saleId)
    {
        return DB::transaction(function () use ($saleId) {

            /** @var Sale $sale */
            $sale = Sale::with([
                'items.producto',
                'client',
                'payments.paymentMethod',
            ])->lockForUpdate()->findOrFail($saleId);

            if ($sale->estado !== 'pagada') {
                throw ValidationException::withMessages([
                    'sale' => 'La venta debe estar PAGADA para emitir factura electrónica.',
                ]);
            }

            $existing = $this->repo->findBySaleId($sale->id);
            if ($existing) {
                if (!$sale->num_factura && !empty($existing->clave_acceso) && strlen($existing->clave_acceso) >= 39) {
                    $serie = substr($existing->clave_acceso, 24, 6);
                    $estab = substr($serie, 0, 3);
                    $pto = substr($serie, 3, 3);
                    $secu = substr($existing->clave_acceso, 30, 9);
                    $sale->num_factura = "{$estab}-{$pto}-{$secu}";
                    $sale->save();
                }
                return $existing;
            }

            /** @var SriConfig $cfg */
            $cfg = $this->configService->getOrFailForUpdate();

            $estab = str_pad((string) ($cfg->codigo_establecimiento ?? '001'), 3, '0', STR_PAD_LEFT);
            $pto = str_pad((string) ($cfg->codigo_punto_emision ?? '001'), 3, '0', STR_PAD_LEFT);

            $seq = (int) ($cfg->secuencial_factura_actual ?? 1);
            if ($seq <= 0)
                $seq = 1;

            $secuencial = str_pad((string) $seq, 9, '0', STR_PAD_LEFT);
            $serie = $estab . $pto;

            $numFactura = "{$estab}-{$pto}-{$secuencial}";
            $sale->num_factura = $sale->num_factura ?: $numFactura;
            $sale->save();


            $fecha = Carbon::parse($sale->fecha_venta)->format('dmY');
            $codDoc = '01';
            $ruc = preg_replace('/\D+/', '', (string) $cfg->ruc);
            $ambiente = (string) ($cfg->ambiente ?? 1); // 1 pruebas, 2 prod
            $tipoEmision = '1';
            $codigoNumerico = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

            $claveSinDv = $fecha . $codDoc . $ruc . $ambiente . $serie . $secuencial . $codigoNumerico . $tipoEmision;
            $dv = $this->modulo11($claveSinDv);
            $claveAcceso = $claveSinDv . $dv;

            $xmlString = $this->buildFacturaXml($sale, $cfg, $claveAcceso, $estab, $pto, $secuencial);

            // ✅ Validación local (para matar el error 35 antes de enviarlo)
            $this->assertWellFormedXml($xmlString, 'generated');

            // Si tienes el XSD guardado, valida aquí (antes de firmar)
            $this->maybeValidateFacturaXsd($xmlString, 'generated');


            $xmlPath = "sri/xml/generados/{$claveAcceso}.xml";
            $this->putDocument($xmlPath, $xmlString, 'generate_xml');

            $invoice = $this->repo->create([
                'sale_id' => $sale->id,
                'clave_acceso' => $claveAcceso,
                'xml_generado_path' => $xmlPath,
                'estado_sri' => 'PENDIENTE_ENVIO',
            ]);

            $cfg->secuencial_factura_actual = $seq + 1;
            $cfg->save();

            return $invoice;
        });
    }

    public function sendAndAuthorizeForSale(int $saleId)
    {
        // 1. Transaction #1: Solo DB para preparación y lock
        $prep = DB::transaction(function () use ($saleId) {
            $sale = Sale::lockForUpdate()->findOrFail($saleId);

            if ($sale->estado !== 'pagada') {
                throw ValidationException::withMessages([
                    'sale' => 'La venta debe estar PAGADA para enviar al SRI.',
                ]);
            }

            $invoice = $this->repo->findBySaleId($sale->id);
            if (!$invoice) {
                $invoice = $this->generateXmlForSale($sale->id);
            }

            if (strtoupper((string) ($invoice->estado_sri ?? '')) === 'AUTORIZADO') {
                return ['status' => 'AUTORIZADO', 'invoice' => $invoice, 'already_authorized' => true];
            }

            $currentState = strtoupper((string) ($invoice->estado_sri ?? ''));
            $skipReception = $this->shouldSkipReception($invoice);

            if (!$skipReception) {
                $signedPath = $invoice->xml_firmado_path ?? null;
                if (!$signedPath || !Storage::disk($this->documentsDisk())->exists($signedPath)) {
                    throw ValidationException::withMessages([
                        'sri' => 'Falta el XML firmado (xml_firmado_path). Ejecuta el Paso 3 (firmado) antes del Paso 4.',
                    ]);
                }

                // Opcional: setear a ENVIANDO para UI
                if ($currentState === 'PENDIENTE_ENVIO') {
                    $invoice->estado_sri = 'EN_PROCESO';
                    $invoice->save();
                }
            }

            $cfg = $this->getCfgOrFail();
            $urls = $this->getWsdlUrls((int) ($cfg->ambiente ?? 1));

            return [
                'invoice' => $invoice,
                'skipReception' => $skipReception,
                'urls' => $urls,
                'claveAcceso' => $invoice->clave_acceso,
            ];
        });

        if (isset($prep['already_authorized'])) {
            return $prep;
        }

        /** @var ElectronicInvoice $invoice */
        $invoice = $prep['invoice'];
        $skipReception = $prep['skipReception'];
        $urls = $prep['urls'];
        $claveAcceso = $prep['claveAcceso'];

        // 2. Fuera de transacción: Recepción
        if (!$skipReception) {
            $signedXml = Storage::disk($this->documentsDisk())->get($invoice->xml_firmado_path);
            $this->assertWellFormedXml($signedXml, 'signed-before-send');
            $this->maybeValidateFacturaXsd($signedXml, 'signed-before-send');
            $recep = $this->callRecepcion($urls['reception_wsdl'], $signedXml);

            $is70 = $this->hasProcessing70($recep);
            $isDup = $this->isDuplicateAlreadyExists($recep);
            $recibida = $this->isRecibida($recep);

            // Actualizar estado según recepción
            if (strtoupper((string) ($recep['estado'] ?? '')) === 'ERROR_RECEPCION' || $is70) {
                DB::transaction(function () use ($invoice, $recep) {
                    $invoice->refresh();
                    $invoice->estado_sri = 'EN_PROCESO';
                    
                    $invoice->mensajes_sri_json = $this->mergeMessages(
                        is_array($invoice->mensajes_sri_json) ? $invoice->mensajes_sri_json : [],
                        $this->extractAllMessages($recep)
                    );
                    $invoice->save();
                });
                return [
                    'status' => 'PROCESSING',
                    'message' => $is70 ? 'SRI procesando (70).' : 'Fallo temporal en recepción.',
                    'invoice' => $invoice->fresh()
                ];
            }

            if ($isDup || $recibida) {
                DB::transaction(function () use ($invoice, $recep) {
                    $invoice->refresh();
                    $invoice->estado_sri = 'ENVIADO';
                    
                    $invoice->mensajes_sri_json = $this->mergeMessages(
                        is_array($invoice->mensajes_sri_json) ? $invoice->mensajes_sri_json : [],
                        $this->extractAllMessages($recep)
                    );
                    $invoice->save();
                });
                // Continuamos a autorización
            } else {
                // Rechazo real
                DB::transaction(function () use ($invoice, $recep) {
                    $invoice->refresh();
                    $invoice->estado_sri = 'RECHAZADO';
                    
                    $invoice->mensajes_sri_json = $this->mergeMessages(
                        is_array($invoice->mensajes_sri_json) ? $invoice->mensajes_sri_json : [],
                        $this->extractAllMessages($recep)
                    );
                    $invoice->save();
                });
                return ['status' => 'REJECTED', 'recep' => $recep, 'invoice' => $invoice->fresh()];
            }
        }

        // 3. Fuera de transacción: Polling Autorización
        $backoffs = [2, 4, 8, 15, 30, 30, 30, 30, 30, 30, 30, 30]; // 12 intentos
        $auth = [];

        foreach ($backoffs as $index => $waitSeconds) {
            $auth = $this->callAutorizacion($urls['authorization_wsdl'], $claveAcceso);
            $estadoAuth = strtoupper((string) ($auth['estado'] ?? ''));

            Log::info("SRI: Polling autorización #" . ($index + 1) . " para $claveAcceso. Estado: $estadoAuth");

            if ($estadoAuth === 'AUTORIZADO' || $estadoAuth === 'NO AUTORIZADO') {
                break;
            }

            if ($index < count($backoffs) - 1) {
                sleep($waitSeconds);
            }
        }

        // 4. Transaction #2: Aplicar resultado final
        return DB::transaction(function () use ($invoice, $auth) {
            $invoice->refresh();

            $estadoAuthFinal = strtoupper((string) ($auth['estado'] ?? ''));

            if ($estadoAuthFinal === 'ERROR_AUTORIZACION' || $this->authStillProcessing($auth)) {
                $invoice->estado_sri = 'EN_PROCESO';
                if (!empty($auth['mensajes'])) {
                    $invoice->mensajes_sri_json = $this->mergeMessages(
                        is_array($invoice->mensajes_sri_json) ? $invoice->mensajes_sri_json : [],
                        $auth['mensajes']
                    );
                }
                $invoice->save();
                return ['status' => 'PROCESSING', 'invoice' => $invoice->fresh(), 'auth' => $auth];
            }

            return $this->applyAuthorizationResult($invoice, $auth);
        });
    }

    public function consultAuthorizationOnce(int $saleId): array
    {
        $invoice = $this->repo->findBySaleId($saleId);
        if (!$invoice) {
            return ['status' => 'ERROR', 'message' => 'Invoice no encontrado'];
        }

        return $this->consultAuthorizationOnceForInvoice($invoice);
    }

    public function consultAuthorizationOnceByInvoiceId(int $invoiceId): array
    {
        $invoice = ElectronicInvoice::find($invoiceId);
        if (!$invoice) {
            return ['status' => 'ERROR', 'message' => 'Invoice no encontrado'];
        }

        return $this->consultAuthorizationOnceForInvoice($invoice);
    }

    private function consultAuthorizationOnceForInvoice(ElectronicInvoice $invoice): array
    {
        if (strtoupper((string) $invoice->estado_sri) === 'AUTORIZADO') {
            return ['status' => 'AUTORIZADO', 'invoice' => $invoice];
        }

        $cfg = $this->getCfgOrFail();
        $urls = $this->getWsdlUrls((int) ($cfg->ambiente ?? 1));

        $auth = $this->callAutorizacion($urls['authorization_wsdl'], $invoice->clave_acceso);

        return DB::transaction(function () use ($invoice, $auth) {
            $invoice->refresh();

            $estadoAuthFinal = strtoupper((string) ($auth['estado'] ?? ''));

            if ($estadoAuthFinal === 'ERROR_AUTORIZACION' || $this->authStillProcessing($auth)) {
                $invoice->estado_sri = 'EN_PROCESO';
                if (!empty($auth['mensajes'])) {
                    $invoice->mensajes_sri_json = $this->mergeMessages(
                        is_array($invoice->mensajes_sri_json) ? $invoice->mensajes_sri_json : [],
                        $auth['mensajes']
                    );
                }
                $invoice->save();
                return ['status' => 'PROCESSING', 'invoice' => $invoice->fresh(), 'auth' => $auth];
            }

            return $this->applyAuthorizationResult($invoice, $auth);
        });
    }





    public function hasProcessing70(array $recep)
    {
        $msgs = $this->extractAllMessages($recep);

        foreach ($msgs as $m) {
            $id = (string) ($m['identificador'] ?? '');
            $msg = strtoupper((string) ($m['mensaje'] ?? ''));
            $inf = strtoupper((string) ($m['informacionAdicional'] ?? ''));

            // Identificador 70 ESPECÍFICO
            if ($id === '70') {
                return true;
            }

            // O mensaje que contenga CLAVE DE ACCESO y PROCES (sin ser duplicado/ya existe)
            $text = $msg . ' ' . $inf;
            if (str_contains($text, 'CLAVE DE ACCESO') && str_contains($text, 'PROCES')) {
                return true;
            }
        }
        return false;
    }

    public function isDuplicateAlreadyExists(array $recep): bool
    {
        $msgs = $this->extractAllMessages($recep);

        // ✅ si hay 70, NO es duplicado
        foreach ($msgs as $m) {
            if ((string) ($m['identificador'] ?? '') === '70') {
                return false;
            }
        }

        foreach ($msgs as $m) {
            $text = strtoupper((string) ($m['mensaje'] ?? '') . ' ' . (string) ($m['informacionAdicional'] ?? ''));
            if (
                str_contains($text, 'YA EXISTE') ||
                str_contains($text, 'COMPROBANTE YA REGISTRADO') ||
                str_contains($text, 'DUPLIC')
            ) {
                return true;
            }
        }
        return false;
    }


    public function isRecibida(array $recep)
    {
        $estado = $this->findValueRecursive($recep, 'estado');
        \Illuminate\Support\Facades\Log::info('DEBUG: isRecibida checking estado', ['estado' => $estado]);
        return strtoupper((string) $estado) === 'RECIBIDA';
    }

    private function authStillProcessing(array $auth): bool
    {
        $estado = strtoupper((string) ($auth['estado'] ?? ''));
        return in_array($estado, [
            'EN PROCESO',
            'EN PROCESAMIENTO',
            'SIN_RESPUESTA',
            'ERROR_AUTORIZACION',
            ''
        ], true);
    }


    private function applyAuthorizationResult(ElectronicInvoice $invoice, array $auth): array
    {
        $estadoAuth = strtoupper((string) ($auth['estado'] ?? ''));
        $mensajesAuth = $auth['mensajes'] ?? [];
        $xmlAutorizado = $auth['xml_autorizado'] ?? null;
        $fechaAut = $auth['fecha_autorizacion'] ?? null;
        $numAut = $auth['numero_autorizacion'] ?? null;

        if ($estadoAuth === 'AUTORIZADO') {
            $invoice->estado_sri = 'AUTORIZADO';
        } elseif ($estadoAuth === 'NO AUTORIZADO') {
            $invoice->estado_sri = 'RECHAZADO';
        } elseif ($estadoAuth === 'SIN_RESPUESTA') {
            // Si el SRI no responde nada útil, lo mantenemos pendiente
            // Podríamos devolver PROCESSING
            $invoice->estado_sri = 'EN_PROCESO';
            $invoice->save();
            return ['status' => 'PROCESSING'];
        } else {
            $invoice->estado_sri = 'RECHAZADO';
        }

        $invoice->mensajes_sri_json = $this->mergeMessages(
            is_array($invoice->mensajes_sri_json) ? $invoice->mensajes_sri_json : [],
            $mensajesAuth
        );

        if ($numAut) {
            $invoice->numero_autorizacion = $numAut;
        }
        if ($fechaAut) {
            $invoice->fecha_autorizacion = $fechaAut;
        }

        if ($invoice->estado_sri === 'AUTORIZADO' && $xmlAutorizado) {
            $pathAut = "sri/xml/autorizados/{$invoice->clave_acceso}.xml";
            $this->putDocument($pathAut, $xmlAutorizado, 'store_authorized_xml');
            $invoice->xml_autorizado_path = $pathAut;
        }

        $invoice->save();

        return ['status' => $invoice->estado_sri, 'invoice' => $invoice->fresh()];
    }

    private function getCfgOrFail(): SriConfig
    {
        $cfg = $this->configService->get();

        if (!$cfg) {
            throw ValidationException::withMessages([
                'sri' => 'No existe configuración SRI. Debes registrarla primero en el panel.',
            ]);
        }

        return $cfg;
    }

    private function getWsdlUrls(int $ambiente): array
    {
        if ($ambiente === 2) {
            return [
                'reception_wsdl' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
                'authorization_wsdl' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
            ];
        }

        return [
            'reception_wsdl' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'authorization_wsdl' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ];
    }

    private function soapClient(string $wsdl): SoapClient
    {
        return new SoapClient($wsdl, [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_MEMORY,
            'connection_timeout' => 20,
            'soap_version' => SOAP_1_1,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
        ]);
    }


    private function callRecepcion(string $wsdl, string $xmlRaw): array
    {
        try {
            $client = $this->soapClient($wsdl);

            // 1) Normalizar XML (evita BOM / espacios antes del prolog)
            $xmlRaw = $this->stripUtf8Bom($xmlRaw);
            $xmlRaw = ltrim($xmlRaw); // IMPORTANTÍSIMO: nada antes de <?xml o <factura

            if (!mb_check_encoding($xmlRaw, 'UTF-8')) {
                $xmlRaw = mb_convert_encoding($xmlRaw, 'UTF-8');
            }

            // 2) Validación local rápida (para detectar basura antes de enviar)
            $this->assertWellFormedXml($xmlRaw, 'signed-before-send');

            /**
             * 3) Enviar como base64Binary SIN hacer base64_encode manual.
             * PHP SOAP se encarga de codificar 1 sola vez cuando usas XSD_BASE64BINARY.
             */
            $xmlParam = new \SoapVar($xmlRaw, XSD_BASE64BINARY);
            $resp = $client->validarComprobante(['xml' => $xmlParam]);

            // 4) Parsear respuesta
            $estado = $resp->RespuestaRecepcionComprobante->estado ?? null;
            if ($estado === null) {
                $estado = $this->findValueRecursive($this->toArray($resp), 'estado');
            }

            $mensajes = [];
            $comprobantes = $resp->RespuestaRecepcionComprobante->comprobantes->comprobante ?? null;

            if ($comprobantes) {
                $items = is_array($comprobantes) ? $comprobantes : [$comprobantes];
                foreach ($items as $c) {
                    $mList = $c->mensajes->mensaje ?? [];
                    if (!is_array($mList))
                        $mList = [$mList];

                    foreach ($mList as $m) {
                        $mensajes[] = [
                            'identificador' => (string) ($m->identificador ?? ''),
                            'mensaje' => (string) ($m->mensaje ?? ''),
                            'informacionAdicional' => (string) ($m->informacionAdicional ?? ''),
                            'tipo' => (string) ($m->tipo ?? 'ERROR'),
                        ];
                    }
                }
            }

            return ['estado' => $estado, 'mensajes' => $mensajes];

        } catch (\Throwable $e) {
            Log::error('SRI Recepcion SOAP error', [
                'wsdl' => $wsdl,
                'error' => $e->getMessage(),
            ]);

            return [
                'estado' => 'ERROR_RECEPCION',
                'mensajes' => [
                    [
                        'identificador' => null,
                        'mensaje' => 'Error al conectar con SRI (recepción).',
                        'informacionAdicional' => $e->getMessage(),
                        'tipo' => 'ERROR'
                    ]
                ]
            ];
        }
    }


    private function xmlToBase64(string $xml): string
    {
        // Si ya parece base64 de XML, no lo re-encodees
        $decoded = base64_decode($xml, true);
        if ($decoded !== false) {
            $head = ltrim($decoded);
            if (str_starts_with($head, '<?xml') || str_starts_with($head, '<factura') || str_starts_with($head, '<')) {
                return $xml;
            }
        }
        return base64_encode($xml);
    }

    private function stripUtf8Bom(string $s): string
    {
        return str_starts_with($s, "\xEF\xBB\xBF") ? substr($s, 3) : $s;
    }

    private function toArray(mixed $obj): array
    {
        return json_decode(json_encode($obj), true) ?: [];
    }


    private function callAutorizacion(string $wsdl, string $claveAcceso): array
    {
        try {
            $client = $this->soapClient($wsdl);

            $resp = $client->autorizacionComprobante([
                'claveAccesoComprobante' => $claveAcceso
            ]);

            $aut = $resp->RespuestaAutorizacionComprobante->autorizaciones->autorizacion ?? null;
            if (is_array($aut)) {
                $aut = $aut[0] ?? null;
            }

            if (!$aut) {
                return [
                    'estado' => 'SIN_RESPUESTA',
                    'fecha_autorizacion' => null,
                    'numero_autorizacion' => null,
                    'xml_autorizado' => null,
                    'mensajes' => [
                        [
                            'identificador' => null,
                            'mensaje' => 'SRI no devolvió autorización aún.',
                            'informacionAdicional' => null,
                            'tipo' => 'INFO',
                        ]
                    ],
                ];
            }

            $estado = $aut->estado ?? null;
            $fechaAut = $aut->fechaAutorizacion ?? null;
            $numAut = $aut->numeroAutorizacion ?? null;
            $xmlAut = $aut->comprobante ?? null;

            $mensajes = [];
            $msgs = $aut->mensajes->mensaje ?? null;

            if ($msgs) {
                $listaMsg = is_array($msgs) ? $msgs : [$msgs];
                foreach ($listaMsg as $m) {
                    $mensajes[] = [
                        'identificador' => $m->identificador ?? null,
                        'mensaje' => $m->mensaje ?? null,
                        'informacionAdicional' => $m->informacionAdicional ?? null,
                        'tipo' => $m->tipo ?? null,
                    ];
                }
            }

            return [
                'estado' => $estado,
                'fecha_autorizacion' => $fechaAut,
                'numero_autorizacion' => $numAut,
                'xml_autorizado' => $xmlAut,
                'mensajes' => $mensajes,
            ];
        } catch (\Throwable $e) {
            Log::error('SRI Autorizacion SOAP error', [
                'wsdl' => $wsdl,
                'clave' => $claveAcceso,
                'error' => $e->getMessage(),
            ]);

            return [
                'estado' => 'ERROR_AUTORIZACION',
                'fecha_autorizacion' => null,
                'numero_autorizacion' => null,
                'xml_autorizado' => null,
                'mensajes' => [
                    [
                        'identificador' => null,
                        'mensaje' => 'Error al conectar con SRI (autorización).',
                        'informacionAdicional' => $e->getMessage(),
                        'tipo' => 'ERROR',
                    ]
                ],
            ];
        }
    }

    private function modulo11(string $base): int
    {
        $multipliers = [2, 3, 4, 5, 6, 7];
        $sum = 0;
        $m = 0;

        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $digit = (int) $base[$i];
            $sum += $digit * $multipliers[$m];
            $m = ($m + 1) % count($multipliers);
        }

        $mod = $sum % 11;
        $dv = 11 - $mod;
        if ($dv === 11)
            return 0;
        if ($dv === 10)
            return 1;
        return $dv;
    }

    private function resolveCompradorSri(Sale $sale): array
    {
        $client = $sale->client;

        if (!$client) {
            return ['07', '9999999999999', 'CONSUMIDOR FINAL'];
        }

        $nombre = trim((string) ($client->nombre ?? ''));
        if ($nombre === '')
            $nombre = 'CLIENTE';

        $rawId = trim((string) ($client->identificacion ?? ''));

        if ($rawId === '') {
            throw ValidationException::withMessages([
                'client_id' => 'El cliente seleccionado no tiene identificación. Registra cédula/RUC/pasaporte o factura como Consumidor Final.',
            ]);
        }

        $idDigits = preg_replace('/\D+/', '', $rawId);

        if ($idDigits === '9999999999999') {
            return ['07', '9999999999999', 'CONSUMIDOR FINAL'];
        }

        if (strlen($idDigits) === 13) {
            return ['04', $idDigits, $nombre];
        }

        if (strlen($idDigits) === 10) {
            return ['05', $idDigits, $nombre];
        }

        return ['06', $rawId, $nombre];
    }



    private function buildFacturaXml(
        Sale $sale,
        SriConfig $cfg,
        string $claveAcceso,
        string $estab,
        string $pto,
        string $secuencial
    ): string {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = false;
        $xml->preserveWhiteSpace = false;

        $el = function ($name, $value = null, $parent = null) use ($xml) {
            $node = $xml->createElement($name);
            if ($value !== null) {
                $node->appendChild(
                    $xml->createTextNode($this->cleanXmlString((string) $value, false))
                );
            }
            if ($parent) {
                $parent->appendChild($node);
            }
            return $node;
        };

        // Root
        $factura = $xml->createElement('factura');
        $factura->setAttribute('id', 'comprobante');
        $factura->setAttribute('version', '1.1.0');
        $xml->appendChild($factura);

        // infoTributaria
        $infoTrib = $el('infoTributaria', null, $factura);
        $el('ambiente', $cfg->ambiente ?? 1, $infoTrib);
        $el('tipoEmision', '1', $infoTrib);
        $el('razonSocial', mb_substr((string) ($cfg->razon_social ?? 'EMISOR'), 0, 300), $infoTrib);
        $el('nombreComercial', mb_substr((string) ($cfg->nombre_comercial ?? ($cfg->razon_social ?? 'EMISOR')), 0, 300), $infoTrib);
        $el('ruc', preg_replace('/\D+/', '', (string) $cfg->ruc), $infoTrib);
        $el('claveAcceso', $claveAcceso, $infoTrib);
        $el('codDoc', '01', $infoTrib);
        $el('estab', $estab, $infoTrib);
        $el('ptoEmi', $pto, $infoTrib);
        $el('secuencial', $secuencial, $infoTrib);
        $el('dirMatriz', $cfg->direccion_matriz ?? 'S/D', $infoTrib);

        // infoFactura
        $infoFac = $el('infoFactura', null, $factura);
        $el('fechaEmision', Carbon::parse($sale->fecha_venta)->format('d/m/Y'), $infoFac);
        $el('dirEstablecimiento', $cfg->direccion_establecimiento ?? ($cfg->direccion_matriz ?? 'S/D'), $infoFac);

        if (!empty($cfg->contribuyente_especial)) {
            $el('contribuyenteEspecial', $cfg->contribuyente_especial, $infoFac);
        }

        $el('obligadoContabilidad', ($cfg->obligado_contabilidad ? 'SI' : 'NO'), $infoFac);

        [$tipoIdComprador, $compradorId, $compradorNombre] = $this->resolveCompradorSri($sale);
        $el('tipoIdentificacionComprador', $tipoIdComprador, $infoFac);
        $el('razonSocialComprador', mb_substr((string) $compradorNombre, 0, 300), $infoFac);
        $el('identificacionComprador', $compradorId, $infoFac);

        // Totales
        $totalSinImpuestos = 0.0;  // base neta sin IVA
        $totalDescuento = 0.0;    // descuento real sumado
        $ivaTotal = 0.0;

        $totalesIva = [];

        foreach ($sale->items as $it) {
            $qty = (float) $it->cantidad;

            $base = round((float) ($it->total ?? 0), 2); // sin IVA
            $pct = round((float) ($it->iva_porcentaje ?? 0), 2);
            $descGross = round((float) ($it->descuento ?? 0), 2);
            $desc = $pct > 0 ? round($descGross / (1 + ($pct / 100)), 2) : $descGross;

            if ($desc < 0)
                $desc = 0;
            if ($qty <= 0)
                $qty = 1;

            // subtotal (antes de descuento) derivado de lo persistido (base + desc)
            $subtotalLinea = round($base + $desc, 2);

            $ivaLinea = round($base * ($pct / 100), 2);

            $totalSinImpuestos += $base;
            $totalDescuento += $desc;
            $ivaTotal += $ivaLinea;

            $codPct = $this->sriCodigoPorcentajeIva($pct);
            if (!isset($totalesIva[$codPct])) {
                $totalesIva[$codPct] = ['base' => 0.0, 'valor' => 0.0];
            }
            $totalesIva[$codPct]['base'] += $base;
            $totalesIva[$codPct]['valor'] += $ivaLinea;
        }

        $el('totalSinImpuestos', number_format($totalSinImpuestos, 2, '.', ''), $infoFac);
        $el('totalDescuento', number_format($totalDescuento, 2, '.', ''), $infoFac);

        $tci = $el('totalConImpuestos', null, $infoFac);
        foreach ($totalesIva as $cod => $t) {
            $ti = $el('totalImpuesto', null, $tci);
            $el('codigo', '2', $ti);
            $el('codigoPorcentaje', (string) $cod, $ti);
            $el('baseImponible', number_format($t['base'], 2, '.', ''), $ti);
            $el('valor', number_format($t['valor'], 2, '.', ''), $ti);
        }

        $el('propina', '0.00', $infoFac);
        $el('importeTotal', number_format($totalSinImpuestos + $ivaTotal, 2, '.', ''), $infoFac);
        $el('moneda', 'DOLAR', $infoFac);

        // pagos
        $paymentMethodName = (string) optional($sale->payments->first()?->paymentMethod)->nombre;
        $forma = $this->mapFormaPagoSri($paymentMethodName);

        $pagos = $el('pagos', null, $infoFac);
        $pago = $el('pago', null, $pagos);
        $el('formaPago', $forma, $pago);
        $el('total', number_format($totalSinImpuestos + $ivaTotal, 2, '.', ''), $pago);
        $el('plazo', '0', $pago);
        $el('unidadTiempo', 'dias', $pago);

        // detalles
        $detallesNode = $el('detalles', null, $factura);

        foreach ($sale->items as $it) {
            $det = $el('detalle', null, $detallesNode);

            $qty = (float) $it->cantidad;
            if ($qty <= 0)
                $qty = 1;

            $base = round((float) ($it->total ?? 0), 2);
            $pct = round((float) ($it->iva_porcentaje ?? 0), 2);
            $descGross = round((float) ($it->descuento ?? 0), 2);
            $desc = $pct > 0 ? round($descGross / (1 + ($pct / 100)), 2) : $descGross;
            if ($desc < 0)
                $desc = 0;

            $subtotalLinea = round($base + $desc, 2);

            // ✅ precio unitario calculado para que cuadre con subtotal y descuento
            $precioUnitarioXml = $qty > 0 ? round($subtotalLinea / $qty, 6) : 0.0;

            $ivaLinea = round($base * ($pct / 100), 2);

            $el('codigoPrincipal', (string) ($it->producto?->codigo_barras ?? $it->producto_id), $det);
            $el('descripcion', mb_substr((string) $it->descripcion, 0, 300), $det);

            $el('cantidad', number_format($qty, 6, '.', ''), $det);
            $el('precioUnitario', number_format($precioUnitarioXml, 6, '.', ''), $det);

            $el('descuento', number_format($desc, 2, '.', ''), $det);
            $el('precioTotalSinImpuesto', number_format($base, 2, '.', ''), $det);

            $imps = $el('impuestos', null, $det);
            $imp = $el('impuesto', null, $imps);

            $el('codigo', '2', $imp);
            $el('codigoPorcentaje', $this->sriCodigoPorcentajeIva($pct), $imp);
            $el('tarifa', number_format($pct, 2, '.', ''), $imp);
            $el('baseImponible', number_format($base, 2, '.', ''), $imp);
            $el('valor', number_format($ivaLinea, 2, '.', ''), $imp);
        }

        return $xml->saveXML();
    }



    private function assertWellFormedXml(string $xml, string $stage): void
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $ok = $dom->loadXML($xml);

        if (!$ok) {
            $errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();

            Log::error("SRI XML MAL FORMADO ($stage)", ['errors' => $errs]);
            throw ValidationException::withMessages(['sri' => "XML mal formado ($stage): " . ($errs[0] ?? 'error')]);
        }
    }

    private function assertSchemaValid(string $xml, string $xsdPath, string $stage): void
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xml, LIBXML_NOBLANKS);

        if (!$dom->schemaValidate($xsdPath)) {
            $errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();

            Log::error("SRI XML NO CUMPLE XSD ($stage)", ['errors' => $errs]);
            throw ValidationException::withMessages(['sri' => "XML no cumple XSD ($stage): " . ($errs[0] ?? 'error')]);
        }
    }

    private function maybeValidateFacturaXsd(string $xml, string $stage): void
    {
        // Ajusta esta ruta si tú guardas el XSD en otro lado
        $xsdPath = storage_path('app/sri/xsd/factura.xsd');

        if (!is_file($xsdPath)) {
            Log::warning("SRI: XSD no encontrado, se omite schemaValidate ($stage)", ['xsd' => $xsdPath]);
            return;
        }

        $this->assertSchemaValid($xml, $xsdPath, $stage);
    }



    private function cleanXmlString(string $str, bool $escape = true): string
    {
        $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $str);
        $str = trim($str);

        if ($escape) {
            return htmlspecialchars($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return $str;
    }


    public function signXmlForSale(int $saleId)
    {
        return DB::transaction(function () use ($saleId) {

            $sale = Sale::lockForUpdate()->findOrFail($saleId);

            if ($sale->estado !== 'pagada') {
                throw ValidationException::withMessages([
                    'sale' => 'La venta debe estar PAGADA para firmar el XML.',
                ]);
            }

            $invoice = $this->repo->findBySaleId($sale->id);
            if (!$invoice) {
                $invoice = $this->generateXmlForSale($sale->id);
            }

            if ($invoice->xml_firmado_path && Storage::disk($this->documentsDisk())->exists($invoice->xml_firmado_path)) {
                return $invoice;
            }

            $unsignedPath = $invoice->xml_generado_path ?? null;
            if (!$unsignedPath || !Storage::disk($this->documentsDisk())->exists($unsignedPath)) {
                throw ValidationException::withMessages([
                    'sri' => 'No existe xml_generado_path para firmar.',
                ]);
            }

            // Ruta del certificado: solo BD
            $cfg = $this->configService->get();

            \Illuminate\Support\Facades\Log::info('SRI: Debug config', [
                'cfg_exists' => $cfg ? 'yes' : 'no',
                'ruta_certificado' => $cfg?->ruta_certificado ?? 'NULL',
                'cert_absolute_path' => $cfg?->cert_absolute_path ?? 'NULL',
            ]);

            $certPath = (string) ($cfg?->cert_absolute_path ?? '');
            \Illuminate\Support\Facades\Log::info("SRI: Usando certificado de DB: " . ($certPath !== '' ? $certPath : 'NULL'));

            // Password: solo BD
            $certPass = (string) ($cfg?->cert_password ?? '');

            if ($certPass === '') {
                throw ValidationException::withMessages([
                    'sri' => 'Falta Contraseña de Certificado (en Configuración SRI).',
                ]);
            }

            if (!is_file($certPath)) {
                \Illuminate\Support\Facades\Log::error("SRI: Fatal - Certificado no es archivo: $certPath");
                throw ValidationException::withMessages([
                    'sri' => "No se encontró el certificado .p12 en: {$certPath}",
                ]);
            }

            $unsignedXml = Storage::disk($this->documentsDisk())->get($unsignedPath);

            $this->assertWellFormedXml($unsignedXml, 'unsigned-before-sign');
            $this->maybeValidateFacturaXsd($unsignedXml, 'unsigned-before-sign');

            $signedXml = $this->signXadesBesSha256($unsignedXml, $certPath, $certPass);

            $this->assertWellFormedXml($signedXml, 'signed-after-sign');
            $this->maybeValidateFacturaXsd($signedXml, 'signed-after-sign');

            $claveAcceso = (string) ($invoice->clave_acceso ?? '');
            $signedPath = "sri/xml/firmados/{$claveAcceso}.xml";

            $this->putDocument($signedPath, $signedXml, 'store_signed_xml');

            // 🐛 DEBUG: Verificar XML firmado
            \Illuminate\Support\Facades\Log::info('🐛 DEBUG XML FIRMADO', [
                'xml_length' => strlen($signedXml),
                'has_claveAcceso' => str_contains($signedXml, '<claveAcceso>'),
                'claveAcceso' => $invoice->clave_acceso,
            ]);

            $invoice->xml_firmado_path = $signedPath;

            if (!$invoice->estado_sri) {
                $invoice->estado_sri = 'PENDIENTE_ENVIO';
            }

            $invoice->save();

            return $invoice->fresh();
        });
    }

    private function signXadesBesSha256(string $xmlString, string $p12Path, string $p12Password): string
    {
        $p12 = file_get_contents($p12Path);
        if ($p12 === false) {
            throw ValidationException::withMessages(['sri' => 'No se pudo leer el archivo .p12']);
        }

        $certs = [];
        if (!openssl_pkcs12_read($p12, $certs, $p12Password)) {
            throw ValidationException::withMessages(['sri' => 'No se pudo abrir el .p12 (clave incorrecta o archivo dañado).']);
        }

        $privateKey = $certs['pkey'] ?? null;
        $publicCert = $certs['cert'] ?? null;

        if (!$privateKey || !$publicCert) {
            throw ValidationException::withMessages(['sri' => 'El .p12 no contiene clave privada y/o certificado.']);
        }

        $certDer = $this->pemToDer($publicCert);
        $certDigestB64 = base64_encode(hash('sha256', $certDer, true));

        $certInfo = openssl_x509_parse($publicCert);
        $issuerName = $this->formatIssuerName($certInfo['issuer'] ?? []);
        $serialNumber = (string) ($certInfo['serialNumber'] ?? '');

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        $doc->loadXML($xmlString, LIBXML_NOBLANKS);

        $root = $doc->documentElement;
        if (!$root->hasAttribute('id')) {
            $root->setAttribute('id', 'comprobante');
        }


        $signatureId = 'Signature-' . bin2hex(random_bytes(8));
        $signedPropsId = 'SignedProperties-' . bin2hex(random_bytes(8));
        $signedPropsRefId = 'SignedPropertiesRef-' . bin2hex(random_bytes(8));
        $ref0Id = 'Reference-' . bin2hex(random_bytes(8));
        $objectId = 'Object-' . bin2hex(random_bytes(8));

        $xadesNS = 'http://uri.etsi.org/01903/v1.3.2#';
        $dsNS = 'http://www.w3.org/2000/09/xmldsig#';

        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

        $dsig->addReference(
            $root,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N],
            [
                'id_name' => 'id',
                'overwrite' => false,

                'force_uri' => true,
                'uri' => '#comprobante',

                'id' => $ref0Id,
            ]
        );

        $sigDoc = $dsig->sigNode->ownerDocument;

        $obj = $sigDoc->createElementNS($dsNS, 'ds:Object');
        $obj->setAttribute('Id', $objectId);

        $qual = $sigDoc->createElementNS($xadesNS, 'xades:QualifyingProperties');
        $qual->setAttribute('Target', "#{$signatureId}");

        $signedProps = $sigDoc->createElementNS($xadesNS, 'xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);

        $ssp = $sigDoc->createElementNS($xadesNS, 'xades:SignedSignatureProperties');
        $signTime = $sigDoc->createElementNS($xadesNS, 'xades:SigningTime', gmdate('Y-m-d\TH:i:s\Z'));
        $ssp->appendChild($signTime);

        $signingCert = $sigDoc->createElementNS($xadesNS, 'xades:SigningCertificate');
        $certNode = $sigDoc->createElementNS($xadesNS, 'xades:Cert');

        $certDigest = $sigDoc->createElementNS($xadesNS, 'xades:CertDigest');
        $dm = $sigDoc->createElementNS($dsNS, 'ds:DigestMethod');
        $dm->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $dv = $sigDoc->createElementNS($dsNS, 'ds:DigestValue', $certDigestB64);
        $certDigest->appendChild($dm);
        $certDigest->appendChild($dv);

        $issuerSerial = $sigDoc->createElementNS($xadesNS, 'xades:IssuerSerial');
        $x509Issuer = $sigDoc->createElementNS($dsNS, 'ds:X509IssuerName', $issuerName);
        $x509Serial = $sigDoc->createElementNS($dsNS, 'ds:X509SerialNumber', $serialNumber);
        $issuerSerial->appendChild($x509Issuer);
        $issuerSerial->appendChild($x509Serial);

        $certNode->appendChild($certDigest);
        $certNode->appendChild($issuerSerial);
        $signingCert->appendChild($certNode);
        $ssp->appendChild($signingCert);

        $sdp = $sigDoc->createElementNS($xadesNS, 'xades:SignedDataObjectProperties');
        $dof = $sigDoc->createElementNS($xadesNS, 'xades:DataObjectFormat');
        $dof->setAttribute('ObjectReference', "#{$ref0Id}");
        $mime = $sigDoc->createElementNS($xadesNS, 'xades:MimeType', 'text/xml');
        $dof->appendChild($mime);
        $sdp->appendChild($dof);


        $signedSigProps = $sigDoc->createElementNS($xadesNS, 'xades:SignedSignatureProperties');
        foreach ($ssp->childNodes as $n) {
            $signedSigProps->appendChild($n->cloneNode(true));
        }

        $signedProps->appendChild($signedSigProps);
        $signedProps->appendChild($sdp);

        $qual->appendChild($signedProps);
        $obj->appendChild($qual);

        $dsig->sigNode->appendChild($obj);

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey(trim($privateKey), false, false);

        $dsig->addReference(
            $signedProps,
            XMLSecurityDSig::SHA256,
            [XMLSecurityDSig::EXC_C14N],
            [
                'id_name' => 'Id',
                'overwrite' => false,
                'force_uri' => true,
                'uri' => "#{$signedPropsId}",
                'type' => 'http://uri.etsi.org/01903#SignedProperties',
                'id' => $signedPropsRefId,
            ]
        );

        $refNodes = $dsig->sigNode->getElementsByTagNameNS($dsNS, 'Reference');

        foreach ($refNodes as $ref) {
            $id = $ref->getAttribute('Id');
            $uri = $ref->getAttribute('URI');

            if ($id === $signedPropsRefId || $uri === "#{$signedPropsId}") {
                $ref->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
                break;
            }
        }

        $dsig->sign($key);

        \Illuminate\Support\Facades\Log::info('🐛 DEBUG: Antes de appendSignature', [
            'sigNode_exists' => $dsig->sigNode ? 'yes' : 'no',
            'root_tagName' => $root->tagName,
        ]);

        $dsig->sigNode->setAttribute('Id', $signatureId);

        $dsig->add509Cert($publicCert, true, false, ['subjectName' => false]);

        $dsig->appendSignature($root);

        $finalXml = $doc->saveXML();

        \Illuminate\Support\Facades\Log::info('🐛 DEBUG: Después de firma', [
            'original_length' => strlen($xmlString),
            'final_length' => strlen($finalXml),
            'has_Signature_tag' => str_contains($finalXml, '<ds:Signature'),
        ]);

        return $finalXml;

    }


    private function pemToDer(string $pem): string
    {
        $pem = preg_replace('/\-+BEGIN CERTIFICATE\-+/', '', $pem);
        $pem = preg_replace('/\-+END CERTIFICATE\-+/', '', $pem);
        $pem = str_replace(["\r", "\n", ' '], '', $pem);
        return base64_decode($pem) ?: '';
    }

    private function formatIssuerName(array $issuer): string
    {
        if (!$issuer)
            return '';
        $order = ['C', 'ST', 'L', 'O', 'OU', 'CN', 'emailAddress'];
        $parts = [];

        foreach ($order as $k) {
            if (isset($issuer[$k])) {
                $v = $issuer[$k];
                $parts[] = $k . '=' . $v;
            }
        }

        foreach ($issuer as $k => $v) {
            if (in_array($k, $order, true))
                continue;
            $parts[] = $k . '=' . $v;
        }

        return implode(',', $parts);
    }

    private function sriCodigoPorcentajeIva(float $pct): string
    {
        if ($pct <= 0)
            return '0';
        if (abs($pct - 12.0) < 0.01)
            return '2';
        if (abs($pct - 14.0) < 0.01)
            return '3';
        if (abs($pct - 15.0) < 0.01)
            return '4';
        return '4';
    }

    private function mapFormaPagoSri(string $metodo): string
    {
        $m = strtoupper(trim($metodo));

        if (str_contains($m, 'TRANSFER'))
            return '17';
        if (str_contains($m, 'CRÉDITO') || str_contains($m, 'CREDITO'))
            return '19';
        if (str_contains($m, 'DÉBITO') || str_contains($m, 'DEBITO'))
            return '16';

        return '01';
    }

    /**
     * Busca una clave de forma recursiva en un array y devuelve su valor.
     */
    private function findValueRecursive(array $array, string $key)
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        foreach ($array as $value) {
            if (is_array($value)) {
                $res = $this->findValueRecursive($value, $key);
                if ($res !== null) {
                    return $res;
                }
            }
        }
        return null;
    }

    /**
     * Mergea mensajes SRI (dedup por identificador/mensaje/info).
     */
    private function mergeMessages(array $current, array $incoming): array
    {
        $all = array_merge($current, $incoming);

        $unique = [];
        foreach ($all as $m) {
            $id = (string) ($m['identificador'] ?? '');
            $msg = (string) ($m['mensaje'] ?? '');
            $inf = (string) ($m['informacionAdicional'] ?? '');
            $hash = md5($id . '|' . $msg . '|' . $inf);
            $unique[$hash] = [
                'identificador' => $id,
                'mensaje' => $msg,
                'informacionAdicional' => $inf,
                'tipo' => (string) ($m['tipo'] ?? 'ERROR'),
            ];
        }

        return array_values($unique);
    }

    /**
     * Extrae todos los mensajes encontrados en cualquier nivel del array de respuesta.
     */
    private function extractAllMessages(array $data): array
    {
        $messages = [];

        // Si es una lista secuencial de mensajes
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $messages = array_merge($messages, $this->extractAllMessages($item));
                }
            }
            return $messages;
        }

        // Si el array actual tiene pinta de ser un mensaje individual
        if (isset($data['identificador']) || isset($data['mensaje'])) {
            $messages[] = [
                'identificador' => (string) ($data['identificador'] ?? ''),
                'mensaje' => (string) ($data['mensaje'] ?? ''),
                'informacionAdicional' => (string) ($data['informacionAdicional'] ?? ''),
                'tipo' => (string) ($data['tipo'] ?? 'ERROR'),
            ];
            // No retornamos aquí porque podría haber más mensajes anidados (poco probable pero posible)
        }

        // Seguir buscando en todas las claves
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Si la clave es 'mensajes', pasamos el contenido directamente
                if ($key === 'mensajes' || $key === 'mensaje') {
                    // Si es un objeto/array que contiene el mensaje
                    if (isset($value['identificador']) || isset($value['mensaje'])) {
                        $messages[] = [
                            'identificador' => (string) ($value['identificador'] ?? ''),
                            'mensaje' => (string) ($value['mensaje'] ?? ''),
                            'informacionAdicional' => (string) ($value['informacionAdicional'] ?? ''),
                            'tipo' => (string) ($value['tipo'] ?? 'ERROR'),
                        ];
                    } else {
                        $messages = array_merge($messages, $this->extractAllMessages($value));
                    }
                } else {
                    $messages = array_merge($messages, $this->extractAllMessages($value));
                }
            }
        }

        // Normalizar a una lista plana sin duplicados por identificador/mensaje
        $unique = [];
        foreach ($messages as $m) {
            $hash = md5($m['identificador'] . $m['mensaje']);
            $unique[$hash] = $m;
        }

        return array_values($unique);
    }

    public function processSaleInvoice(int $saleId): array
    {
        // Asegura XML generado y firmado (idempotente)
        $this->generateXmlForSale($saleId);
        $invoice = $this->signXmlForSale($saleId);

        $invoice->refresh();
        $estado = strtoupper((string) ($invoice->estado_sri ?? ''));

        if ($estado === 'AUTORIZADO') {
            return ['status' => 'AUTORIZADO', 'invoice' => $invoice];
        }

        $cfg = $this->getCfgOrFail();
        $urls = $this->getWsdlUrls((int) ($cfg->ambiente ?? 1));

        // 1) Recepción SOLO si aún no fue enviado
        if (!in_array($estado, ['ENVIADO', 'EN_PROCESO'], true)) {
            $signedXml = Storage::disk($this->documentsDisk())->get($invoice->xml_firmado_path);
            $recep = $this->callRecepcion($urls['reception_wsdl'], $signedXml);
            Log::info('SRI RECEPCION RAW', [
                'clave' => $invoice->clave_acceso,
                'resp' => $recep,
            ]);


            $decision = $this->applyReceptionResult($invoice->id, $recep);

            if (($decision['status'] ?? '') === 'PROCESSING') {
                return ['status' => 'PROCESSING', 'invoice' => $decision['invoice'] ?? $invoice, 'recep' => $recep];
            }

            if (in_array(strtoupper((string) ($decision['status'] ?? '')), ['RECHAZADO', 'REJECTED'], true)) {
                return ['status' => 'RECHAZADO', 'invoice' => $decision['invoice'] ?? $invoice, 'recep' => $recep];
            }

            // Si quedó ENVIADO, seguimos a autorización
            $invoice = ($decision['invoice'] ?? $invoice)->fresh();
        }

        // 2) Autorización (una llamada). Si está procesando => PROCESSING y el Job reintenta
        $auth = $this->callAutorizacion($urls['authorization_wsdl'], $invoice->clave_acceso);

        return DB::transaction(function () use ($invoice, $auth) {
            $invoice->refresh();

            $estadoAuthFinal = strtoupper((string) ($auth['estado'] ?? ''));

            if ($estadoAuthFinal === 'ERROR_AUTORIZACION' || $this->authStillProcessing($auth)) {
                $invoice->estado_sri = 'EN_PROCESO';
                if (!empty($auth['mensajes'])) {
                    $invoice->mensajes_sri_json = $this->mergeMessages(
                        is_array($invoice->mensajes_sri_json) ? $invoice->mensajes_sri_json : [],
                        $auth['mensajes']
                    );
                }
                $invoice->save();
                return ['status' => 'PROCESSING', 'invoice' => $invoice->fresh(), 'auth' => $auth];
            }

            return $this->applyAuthorizationResult($invoice, $auth);
        });
    }

    private function applyReceptionResult(int $invoiceId, array $recep): array
    {
        return DB::transaction(function () use ($invoiceId, $recep) {
            $invoice = ElectronicInvoice::lockForUpdate()->findOrFail($invoiceId);

            $estadoRec = strtoupper((string) ($recep['estado'] ?? ''));
            $mensajes = $this->extractAllMessages($recep);

            $is70 = $this->hasProcessing70($recep);
            $isDup = $this->isDuplicateAlreadyExists($recep);
            $recibida = ($estadoRec === 'RECIBIDA');

            $invoice->mensajes_sri_json = $this->mergeMessages(
                is_array($invoice->mensajes_sri_json) ? $invoice->mensajes_sri_json : [],
                $mensajes
            );

            if ($is70) {
                $invoice->estado_sri = 'EN_PROCESO';
                $invoice->save();
                return ['status' => 'PROCESSING', 'invoice' => $invoice->fresh()];
            }

            // Fallo tecnico/transitorio en recepcion SRI: no es rechazo final.
            if ($estadoRec === 'ERROR_RECEPCION') {
                $invoice->estado_sri = 'EN_PROCESO';
                $invoice->save();
                return ['status' => 'PROCESSING', 'invoice' => $invoice->fresh()];
            }

            if ($recibida || $isDup) {
                $invoice->estado_sri = 'ENVIADO';
                $invoice->save();
                return ['status' => 'ENVIADO', 'invoice' => $invoice->fresh()];
            }

            $invoice->estado_sri = 'RECHAZADO';
            $invoice->save();
            return ['status' => 'RECHAZADO', 'invoice' => $invoice->fresh()];
        });
    }

}
