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

            $dir = "sri/xml/generados";
            Storage::disk('local')->makeDirectory($dir);

            $xmlPath = "{$dir}/{$claveAcceso}.xml";
            Storage::disk('local')->put($xmlPath, $xmlString);

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
        return DB::transaction(function () use ($saleId) {

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
                return $invoice;
            }

            // --- LOGICA DE ENVIO Y AUTORIZACION (HOTFIX ERROR 70) ---

            // Si ya está ENVIADO o EN_PROCESO, saltamos la recepción para sólo consultar autorización
            $currentState = strtoupper((string) ($invoice->estado_sri ?? ''));
            $skipReception = in_array($currentState, ['ENVIADO', 'EN_PROCESO'], true);

            if (!$skipReception) {
                $signedPath = $invoice->xml_firmado_path ?? null;
                if (!$signedPath || !Storage::disk('local')->exists($signedPath)) {
                    throw ValidationException::withMessages([
                        'sri' => 'Falta el XML firmado (xml_firmado_path). Ejecuta el Paso 3 (firmado) antes del Paso 4.',
                    ]);
                }

                $cfg = $this->getCfgOrFail();
                $urls = $this->getWsdlUrls((int) ($cfg->ambiente ?? 1));
                $signedXml = Storage::disk('local')->get($signedPath);

                $recep = $this->callRecepcion($urls['reception_wsdl'], $signedXml);

                \Illuminate\Support\Facades\Log::info('SRI: Recep Raw Response', ['recep' => $recep]);

                $is70 = $this->hasProcessing70($recep);
                $isDup = $this->isDuplicateAlreadyExists($recep);
                $recibida = $this->isRecibida($recep);

                if ($isDup) {
                    \Illuminate\Support\Facades\Log::info('SRI: DUPLICATE_ALREADY_EXISTS detectado. Saltando recepción y consultando autorización.', [
                        'clave' => $invoice->clave_acceso
                    ]);

                    $invoice->estado_sri = 'ENVIADO';
                    $invoice->mensajes_sri_json = $this->extractAllMessages($recep);
                    $invoice->save();

                    // IMPORTANTE: no retornar; seguimos al bloque de autorización
                    $skipReception = true;
                }

                if ($is70) {
                    \Illuminate\Support\Facades\Log::info('SRI: PROCESSING_RECEPTION_70 detectado. Retorno temprano.', ['clave' => $invoice->clave_acceso]);
                    $invoice->estado_sri = 'EN_PROCESO';
                    $invoice->mensajes_sri_json = $this->extractAllMessages($recep);
                    $invoice->save();
                    return [
                        'status' => 'PROCESSING',
                        'message' => 'SRI está procesando el comprobante. Reintenta en unos segundos.',
                        'invoice' => $invoice
                    ];
                }

                if ($recibida) {
                    $invoice->estado_sri = 'ENVIADO';
                    $invoice->mensajes_sri_json = $this->extractAllMessages($recep);
                    $invoice->save();

                    \Illuminate\Support\Facades\Log::info('SRI: Comprobante Recibido. Continuando a Autorizacion.', [
                        'clave' => $invoice->clave_acceso
                    ]);
                } else {
                    // Rechazo real (no es recibida, ni 70, ni duplicado)
                    $invoice->estado_sri = 'RECHAZADO';
                    $invoice->mensajes_sri_json = $this->extractAllMessages($recep);
                    $invoice->save();
                    return ['status' => 'REJECTED', 'recep' => $recep, 'invoice' => $invoice];
                }
            }

            // --- ETAPA AUTORIZACION (CON POLLING) ---
            $cfg = $this->getCfgOrFail();
            $urls = $this->getWsdlUrls((int) ($cfg->ambiente ?? 1));
            $claveAcceso = (string) ($invoice->clave_acceso ?? '');

            if ($claveAcceso === '') {
                return ['status' => 'ERROR', 'message' => 'Sin clave de acceso'];
            }

            // Polling de autorización
            $maxRetries = $skipReception ? 3 : 10;
            $auth = [];
            for ($i = 1; $i <= $maxRetries; $i++) {
                $auth = $this->callAutorizacion($urls['authorization_wsdl'], $claveAcceso);
                $estadoAuth = strtoupper((string) ($auth['estado'] ?? ''));

                \Illuminate\Support\Facades\Log::info("SRI: Polling autorización #$i para $claveAcceso. Estado: $estadoAuth");

                if ($estadoAuth === 'AUTORIZADO' || $estadoAuth === 'NO AUTORIZADO') {
                    break;
                }

                if ($i < $maxRetries) {
                    if ($skipReception) {
                        // Reintentos suaves (backoff): 2s, 4s, 8s
                        $wait = pow(2, $i);
                        sleep($wait);
                    } else {
                        // Esperar 1 segundo antes de reintentar
                        usleep(1000000);
                    }
                }
            }

            if ($this->authStillProcessing($auth)) {
                $invoice->estado_sri = 'EN_PROCESO';
                $invoice->save();
                return ['status' => 'PROCESSING', 'auth' => $auth];
            }

            // Procesar resultado final (Autorizado / Rechazado / etc)
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

    public function isDuplicateAlreadyExists(array $recep)
    {
        $msgs = $this->extractAllMessages($recep);

        foreach ($msgs as $m) {
            $msg = strtoupper((string) ($m['mensaje'] ?? ''));
            $inf = strtoupper((string) ($m['informacionAdicional'] ?? ''));

            $text = $msg . ' ' . $inf;
            // Detección de duplicado real (excluyendo "PROCEDIMIENTO: SI" que sale en Error 70)
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
        // Estados que indican que hay que seguir esperando
        return in_array($estado, ['EN PROCESO', 'EN PROCESAMIENTO', 'SIN_RESPUESTA', '']);
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

        $invoice->mensajes_sri_json = $mensajesAuth;

        if ($numAut) {
            $invoice->numero_autorizacion = $numAut;
        }
        if ($fechaAut) {
            $invoice->fecha_autorizacion = $fechaAut;
        }

        if ($invoice->estado_sri === 'AUTORIZADO' && $xmlAutorizado) {
            $dir = "sri/xml/autorizados";
            Storage::disk('local')->makeDirectory($dir);
            $pathAut = "{$dir}/{$invoice->clave_acceso}.xml";
            Storage::disk('local')->put($pathAut, $xmlAutorizado);
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
        ]);
    }

    private function callRecepcion(string $wsdl, string $xmlRaw): array
    {
        try {
            $client = $this->soapClient($wsdl);

            \Illuminate\Support\Facades\Log::info('🐛 DEBUG XML pre-envío', [
                'xml_length' => strlen($xmlRaw),
                'has_claveAcceso' => str_contains($xmlRaw, '<claveAcceso>'),
            ]);

            $resp = $client->validarComprobante(['xml' => $xmlRaw]);

            $estado = $resp->RespuestaRecepcionComprobante->estado ?? null;
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

            $finalResponse = [
                'estado' => $estado,
                'mensajes' => $mensajes
            ];

            \Illuminate\Support\Facades\Log::info('SRI: callRecepcion Return', $finalResponse);

            return $finalResponse;

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('SRI Recepcion SOAP error', [
                'wsdl' => $wsdl,
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500)
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



    private function buildFacturaXml(Sale $sale, SriConfig $cfg, string $claveAcceso, string $estab, string $pto, string $secuencial): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = false;
        $xml->preserveWhiteSpace = false;

        // Función helper para crear elementos sin namespace (estilo SRI estándar)
        $el = function ($name, $value = null, $parent = null) use ($xml) {
            $node = $xml->createElement($name);
            if ($value !== null) {
                // Usamos createTextNode para evitar problemas con caracteres especiales (escapado automático)
                $node->appendChild($xml->createTextNode($this->cleanXmlString((string) $value, false)));
            }
            if ($parent) {
                $parent->appendChild($node);
            }
            return $node;
        };

        // Nodo raíz (sin namespace para maximizar compatibilidad con validadores antiguos/picky)
        $factura = $xml->createElement('factura');
        $factura->setAttribute('id', 'comprobante');
        $factura->setAttribute('version', '1.1.0');
        $xml->appendChild($factura);

        // --- infoTributaria ---
        $infoTrib = $el('infoTributaria', null, $factura);
        $el('ambiente', $cfg->ambiente ?? 1, $infoTrib);
        $el('tipoEmision', '1', $infoTrib);
        $el('razonSocial', $cfg->razon_social ?? 'EMISOR', $infoTrib);
        $el('nombreComercial', $cfg->nombre_comercial ?? ($cfg->razon_social ?? 'EMISOR'), $infoTrib);
        $el('ruc', preg_replace('/\D+/', '', (string) $cfg->ruc), $infoTrib);
        $el('claveAcceso', $claveAcceso, $infoTrib);
        $el('codDoc', '01', $infoTrib);
        $el('estab', $estab, $infoTrib);
        $el('ptoEmi', $pto, $infoTrib);
        $el('secuencial', $secuencial, $infoTrib);
        $el('dirMatriz', $cfg->direccion_matriz ?? 'S/D', $infoTrib);

        // --- infoFactura ---
        $infoFac = $el('infoFactura', null, $factura);
        $fechaEmision = Carbon::parse($sale->fecha_venta)->format('d/m/Y');
        $el('fechaEmision', $fechaEmision, $infoFac);
        $el('dirEstablecimiento', $cfg->direccion_establecimiento ?? ($cfg->direccion_matriz ?? 'S/D'), $infoFac);

        if (!empty($cfg->contribuyente_especial)) {
            $el('contribuyenteEspecial', $cfg->contribuyente_especial, $infoFac);
        }

        $el('obligadoContabilidad', ($cfg->obligado_contabilidad ? 'SI' : 'NO'), $infoFac);

        [$tipoIdComprador, $compradorId, $compradorNombre] = $this->resolveCompradorSri($sale);
        $el('tipoIdentificacionComprador', $tipoIdComprador, $infoFac);
        $el('razonSocialComprador', $compradorNombre, $infoFac);
        $el('identificacionComprador', $compradorId, $infoFac);

        $totalSinImpuestosN = 0.0;
        $ivaTotalN = 0.0;
        $totalesIva = [];

        foreach ($sale->items as $it) {
            $base = round((float) $it->total, 2);
            $pct = round((float) ($it->iva_porcentaje ?? 0), 2);
            $ivaLinea = round($base * ($pct / 100), 2);

            $totalSinImpuestosN += $base;
            $ivaTotalN += $ivaLinea;

            $codPct = $this->sriCodigoPorcentajeIva($pct);
            if (!isset($totalesIva[$codPct])) {
                $totalesIva[$codPct] = ['tarifa' => $pct, 'base' => 0.0, 'valor' => 0.0];
            }
            $totalesIva[$codPct]['base'] += $base;
            $totalesIva[$codPct]['valor'] += $ivaLinea;
        }

        $el('totalSinImpuestos', number_format($totalSinImpuestosN, 2, '.', ''), $infoFac);
        $el('totalDescuento', '0.00', $infoFac);

        // totalConImpuestos
        $tci = $el('totalConImpuestos', null, $infoFac);
        foreach ($totalesIva as $cod => $t) {
            $ti = $el('totalImpuesto', null, $tci);
            $el('codigo', '2', $ti);
            $el('codigoPorcentaje', (string) $cod, $ti);
            $el('baseImponible', number_format($t['base'], 2, '.', ''), $ti);
            $el('valor', number_format($t['valor'], 2, '.', ''), $ti);
        }

        $el('propina', '0.00', $infoFac);
        $el('importeTotal', number_format($totalSinImpuestosN + $ivaTotalN, 2, '.', ''), $infoFac);
        $el('moneda', 'DOLAR', $infoFac);

        // pagos
        $pagos = $el('pagos', null, $infoFac);
        $pago = $el('pago', null, $pagos);
        $el('formaPago', '01', $pago);
        $el('total', number_format($totalSinImpuestosN + $ivaTotalN, 2, '.', ''), $pago);
        $el('plazo', '0', $pago);
        $el('unidadTiempo', 'dias', $pago);

        // --- detalles ---
        $detallesNode = $el('detalles', null, $factura);
        foreach ($sale->items as $it) {
            $det = $el('detalle', null, $detallesNode);
            $el('codigoPrincipal', $it->producto?->codigo_barras ?? $it->producto_id, $det);
            $el('descripcion', $it->descripcion, $det);
            $el('cantidad', number_format($it->cantidad, 6, '.', ''), $det);
            $el('precioUnitario', number_format($it->precio_unitario, 6, '.', ''), $det);
            $el('descuento', '0.00', $det);
            $el('precioTotalSinImpuesto', number_format($it->total, 2, '.', ''), $det);

            $imps = $el('impuestos', null, $det);
            $imp = $el('impuesto', null, $imps);
            $pct = (float) ($it->iva_porcentaje ?? 0);
            $el('codigo', '2', $imp);
            $el('codigoPorcentaje', $this->sriCodigoPorcentajeIva($pct), $imp);
            $el('tarifa', number_format($pct, 0, '.', ''), $imp);
            $el('baseImponible', number_format($it->total, 2, '.', ''), $imp);
            $el('valor', number_format($it->total * ($pct / 100), 2, '.', ''), $imp);
        }

        return $xml->saveXML();
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

            if ($invoice->xml_firmado_path && Storage::disk('local')->exists($invoice->xml_firmado_path)) {
                return $invoice;
            }

            $unsignedPath = $invoice->xml_generado_path ?? null;
            if (!$unsignedPath || !Storage::disk('local')->exists($unsignedPath)) {
                throw ValidationException::withMessages([
                    'sri' => 'No existe xml_generado_path para firmar.',
                ]);
            }

            // Ruta del certificado: BD > Env (Fallback)
            $cfg = $this->configService->get();

            \Illuminate\Support\Facades\Log::info('SRI: Debug config', [
                'cfg_exists' => $cfg ? 'yes' : 'no',
                'ruta_certificado' => $cfg?->ruta_certificado ?? 'NULL',
                'cert_absolute_path' => $cfg?->cert_absolute_path ?? 'NULL',
            ]);

            $certPath = (string) ($cfg?->cert_absolute_path ?? $this->resolveCertPath((string) env('SRI_CERT_PATH')));

            if (!$certPath || !file_exists($certPath)) {
                \Illuminate\Support\Facades\Log::warning("SRI: Certificado no encontrado en DB path: " . ($certPath ?? 'NULL') . ". Intentando fallback ENV.");
                // Si no existe, intentamos fallback puro por si acaso (aunque resolveCertPath ya lo hace)
                $certPath = $this->resolveCertPath((string) env('SRI_CERT_PATH'));
            } else {
                \Illuminate\Support\Facades\Log::info("SRI: Usando certificado de DB: $certPath");
            }

            // Password: DB > Env
            $certPass = (string) ($cfg?->cert_password ?? env('SRI_CERT_PASSWORD', ''));

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

            $unsignedXml = Storage::disk('local')->get($unsignedPath);

            $signedXml = $this->signXadesBesSha256($unsignedXml, $certPath, $certPass);

            $dir = "sri/xml/firmados";
            Storage::disk('local')->makeDirectory($dir);

            $claveAcceso = (string) ($invoice->clave_acceso ?? '');
            $signedPath = "{$dir}/{$claveAcceso}.xml";

            Storage::disk('local')->put($signedPath, $signedXml);

            // 🐛 DEBUG: Verificar XML firmado
            \Illuminate\Support\Facades\Log::info('🐛 DEBUG XML FIRMADO', [
                'xml_length' => strlen($signedXml),
                'has_claveAcceso' => str_contains($signedXml, '<claveAcceso>'),
                'xml_full' => $signedXml, // ⚠️ Ver XML completo para debug
            ]);

            $invoice->xml_firmado_path = $signedPath;

            if (!$invoice->estado_sri) {
                $invoice->estado_sri = 'PENDIENTE_ENVIO';
            }

            $invoice->save();

            return $invoice->fresh();
        });
    }

    private function resolveCertPath(string $envPath): string
    {
        if ($envPath === '')
            return storage_path('app/sri/certs/certificado.p12');

        if (str_starts_with($envPath, '/')) {
            return $envPath;
        }

        if (is_file(base_path($envPath))) {
            return base_path($envPath);
        }

        if (is_file(storage_path($envPath))) {
            return storage_path($envPath);
        }

        if (is_file(storage_path('app/' . ltrim($envPath, '/')))) {
            return storage_path('app/' . ltrim($envPath, '/'));
        }

        return base_path($envPath);
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
}
