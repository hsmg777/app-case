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
    ) {}

    public function getInvoiceBySaleId(int $saleId): ?ElectronicInvoice
    {
        return $this->repo->findBySaleId($saleId);
    }

    public function markInvoiceError(int $saleId, string $message): void
    {
        $inv = $this->repo->findBySaleId($saleId);
        if (!$inv) return;

        $inv->mensaje_error = mb_substr($message, 0, 2000);
        // Opcional: para que se vea como pendiente de reintento en UI
        if (!in_array(strtoupper((string)$inv->estado_sri), ['AUTORIZADO','RECHAZADO'], true)) {
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

            $estab = str_pad((string)($cfg->codigo_establecimiento ?? '001'), 3, '0', STR_PAD_LEFT);
            $pto   = str_pad((string)($cfg->codigo_punto_emision ?? '001'), 3, '0', STR_PAD_LEFT);

            $seq = (int)($cfg->secuencial_factura_actual ?? 1);
            if ($seq <= 0) $seq = 1;

            $secuencial = str_pad((string)$seq, 9, '0', STR_PAD_LEFT);
            $serie = $estab . $pto;

            $numFactura = "{$estab}-{$pto}-{$secuencial}";
            $sale->num_factura = $sale->num_factura ?: $numFactura;
            $sale->save();

    
            $fecha = Carbon::parse($sale->fecha_venta)->format('dmY');
            $codDoc = '01'; 
            $ruc = preg_replace('/\D+/', '', (string)$cfg->ruc);
            $ambiente = (string)($cfg->ambiente ?? 1); // 1 pruebas, 2 prod
            $tipoEmision = '1';
            $codigoNumerico = str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

            $claveSinDv = $fecha.$codDoc.$ruc.$ambiente.$serie.$secuencial.$codigoNumerico.$tipoEmision;
            $dv = $this->modulo11($claveSinDv);
            $claveAcceso = $claveSinDv.$dv;

            $xmlString = $this->buildFacturaXml($sale, $cfg, $claveAcceso, $estab, $pto, $secuencial);

            $dir = "sri/xml/generados";
            Storage::disk('local')->makeDirectory($dir);

            $xmlPath = "{$dir}/{$claveAcceso}.xml";
            Storage::disk('local')->put($xmlPath, $xmlString);

            $invoice = $this->repo->create([
                'sale_id'           => $sale->id,
                'clave_acceso'      => $claveAcceso,
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

            if (strtoupper((string)($invoice->estado_sri ?? '')) === 'AUTORIZADO') {
                return $invoice;
            }

            $signedPath = $invoice->xml_firmado_path ?? null;
            if (!$signedPath || !Storage::disk('local')->exists($signedPath)) {
                throw ValidationException::withMessages([
                    'sri' => 'Falta el XML firmado (xml_firmado_path). Ejecuta el Paso 3 (firmado) antes del Paso 4.',
                ]);
            }

            $cfg = $this->getCfgOrFail();
            $urls = $this->getWsdlUrls((int)($cfg->ambiente ?? 1));

            $signedXml = Storage::disk('local')->get($signedPath);

            $recep = $this->callRecepcion($urls['reception_wsdl'], $signedXml);

            $estadoRecep = strtoupper((string)($recep['estado'] ?? ''));
            $mensajesRecep = $recep['mensajes'] ?? [];

            if ($estadoRecep === 'RECIBIDA') {
                $invoice->estado_sri = 'ENVIADO'; 
            } elseif ($estadoRecep === 'DEVUELTA') {
                $invoice->estado_sri = 'RECHAZADO';
            } else {
                $invoice->estado_sri = 'RECHAZADO';
            }

            $invoice->mensajes_sri_json = $mensajesRecep;
            $invoice->save();

            if ($estadoRecep !== 'RECIBIDA') {
                return $invoice->fresh();
            }

            $claveAcceso = (string)($invoice->clave_acceso ?? '');
            if ($claveAcceso === '') {
                throw ValidationException::withMessages([
                    'sri' => 'No existe clave_acceso en electronic_invoices.',
                ]);
            }

            $auth = $this->callAutorizacion($urls['authorization_wsdl'], $claveAcceso);
            $estadoAuth = strtoupper((string)($auth['estado'] ?? ''));
            $mensajesAuth = $auth['mensajes'] ?? [];
            $xmlAutorizado = $auth['xml_autorizado'] ?? null;
            $fechaAut = $auth['fecha_autorizacion'] ?? null;
            $numAut = $auth['numero_autorizacion'] ?? null;

            if ($estadoAuth === 'AUTORIZADO') {
                $invoice->estado_sri = 'AUTORIZADO';
            } elseif ($estadoAuth === 'NO AUTORIZADO') {
                $invoice->estado_sri = 'RECHAZADO';
            } elseif ($estadoAuth === 'SIN_RESPUESTA') {
                $invoice->estado_sri = 'ENVIADO'; 
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

                $pathAut = "{$dir}/{$claveAcceso}.xml";
                Storage::disk('local')->put($pathAut, $xmlAutorizado);

                $invoice->xml_autorizado_path = $pathAut;
            }

            $invoice->save();

            return $invoice->fresh();
        });
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
                'reception_wsdl'     => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
                'authorization_wsdl' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
            ];
        }

        return [
            'reception_wsdl'     => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
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

            $xmlParam = new \SoapVar($xmlRaw, \XSD_BASE64BINARY);

            $resp = $client->validarComprobante(['xml' => $xmlParam]);

            $estado = $resp->RespuestaRecepcionComprobante->estado ?? null;

            $mensajes = [];
            $comprobantes = $resp->RespuestaRecepcionComprobante->comprobantes->comprobante ?? null;

            if ($comprobantes) {
                $listaComp = is_array($comprobantes) ? $comprobantes : [$comprobantes];
                foreach ($listaComp as $c) {
                    $msgs = $c->mensajes->mensaje ?? null;
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
                }
            }

            return [
                'estado' => $estado,
                'mensajes' => $mensajes,
            ];
        } catch (\Throwable $e) {
            Log::error('SRI Recepcion SOAP error', [
                'wsdl' => $wsdl,
                'error' => $e->getMessage(),
            ]);

            return [
                'estado' => 'ERROR_RECEPCION',
                'mensajes' => [[
                    'identificador' => null,
                    'mensaje' => 'Error al conectar con SRI (recepción).',
                    'informacionAdicional' => $e->getMessage(),
                    'tipo' => 'ERROR',
                ]],
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
                    'mensajes' => [[
                        'identificador' => null,
                        'mensaje' => 'SRI no devolvió autorización aún.',
                        'informacionAdicional' => null,
                        'tipo' => 'INFO',
                    ]],
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
                'mensajes' => [[
                    'identificador' => null,
                    'mensaje' => 'Error al conectar con SRI (autorización).',
                    'informacionAdicional' => $e->getMessage(),
                    'tipo' => 'ERROR',
                ]],
            ];
        }
    }

    private function modulo11(string $base): int
    {
        $multipliers = [2,3,4,5,6,7];
        $sum = 0;
        $m = 0;

        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $digit = (int)$base[$i];
            $sum += $digit * $multipliers[$m];
            $m = ($m + 1) % count($multipliers);
        }

        $mod = $sum % 11;
        $dv = 11 - $mod;
        if ($dv === 11) return 0;
        if ($dv === 10) return 1;
        return $dv;
    }

    private function resolveCompradorSri(Sale $sale): array
    {
        $client = $sale->client;

        if (!$client) {
            return ['07', '9999999999999', 'CONSUMIDOR FINAL'];
        }

        $nombre = trim((string)($client->nombre ?? ''));
        if ($nombre === '') $nombre = 'CLIENTE';

        $rawId = trim((string)($client->identificacion ?? ''));

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
        $razonSocial = $cfg->razon_social ?? 'EMISOR';
        $nombreComercial = $cfg->nombre_comercial ?? $razonSocial;
        $dirMatriz = $cfg->direccion_matriz ?? 'S/D';

       [$tipoIdComprador, $compradorId, $compradorNombre] = $this->resolveCompradorSri($sale);

        $fechaEmision = Carbon::parse($sale->fecha_venta)->format('d/m/Y');

       
        $totalSinImpuestosN = 0.0;
        $totalDescuentoN = 0.0;
        $ivaTotalN = 0.0;
        $totalesIva = [];

        foreach ($sale->items as $it) {
            $base = round((float) $it->total, 2);
            $desc = round((float) ($it->descuento ?? 0), 2);
            $pct  = round((float) ($it->iva_porcentaje ?? 0), 2);

            $ivaLinea = round($base * ($pct / 100), 2);

            $totalSinImpuestosN += $base;
            $totalDescuentoN += $desc;
            $ivaTotalN += $ivaLinea;

            $codigoPorcentajeLinea = $this->sriCodigoPorcentajeIva($pct);

            if (!isset($totalesIva[$codigoPorcentajeLinea])) {
                $totalesIva[$codigoPorcentajeLinea] = [
                    'tarifa' => number_format($pct, 2, '.', ''),
                    'base'   => 0.0,
                    'valor'  => 0.0,
                ];
            }

            $totalesIva[$codigoPorcentajeLinea]['base']  += $base;
            $totalesIva[$codigoPorcentajeLinea]['valor'] += $ivaLinea;
        }

        $totalSinImpuestos = number_format($totalSinImpuestosN, 2, '.', '');
        $totalDescuento    = number_format($totalDescuentoN, 2, '.', '');
        $ivaTotal          = number_format($ivaTotalN, 2, '.', '');
        $importeTotal      = number_format($totalSinImpuestosN + $ivaTotalN, 2, '.', '');

        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $factura = $xml->createElement('factura');
        $factura->setAttribute('id', 'comprobante');
        $factura->setAttribute('version', '1.1.0');
        $xml->appendChild($factura);

     
        $infoTrib = $xml->createElement('infoTributaria');
        $factura->appendChild($infoTrib);

        $infoTrib->appendChild($xml->createElement('ambiente', (string)($cfg->ambiente ?? 1)));
        $infoTrib->appendChild($xml->createElement('tipoEmision', '1'));
        $infoTrib->appendChild($xml->createElement('razonSocial', $razonSocial));
        $infoTrib->appendChild($xml->createElement('nombreComercial', $nombreComercial));
        $infoTrib->appendChild($xml->createElement('ruc', preg_replace('/\D+/', '', (string)$cfg->ruc)));
        $infoTrib->appendChild($xml->createElement('claveAcceso', $claveAcceso));
        $infoTrib->appendChild($xml->createElement('codDoc', '01'));
        $infoTrib->appendChild($xml->createElement('estab', $estab));
        $infoTrib->appendChild($xml->createElement('ptoEmi', $pto));
        $infoTrib->appendChild($xml->createElement('secuencial', $secuencial));
        $infoTrib->appendChild($xml->createElement('dirMatriz', $dirMatriz));

   
        $infoFac = $xml->createElement('infoFactura');
        $factura->appendChild($infoFac);

        $infoFac->appendChild($xml->createElement('fechaEmision', $fechaEmision));
        $infoFac->appendChild($xml->createElement('dirEstablecimiento', $cfg->direccion_establecimiento ?? $dirMatriz));
        $infoFac->appendChild($xml->createElement('obligadoContabilidad', ($cfg->obligado_contabilidad ? 'SI' : 'NO')));
        $infoFac->appendChild($xml->createElement('tipoIdentificacionComprador', $tipoIdComprador));
        $infoFac->appendChild($xml->createElement('razonSocialComprador', $compradorNombre));
        $infoFac->appendChild($xml->createElement('identificacionComprador', $compradorId));
        $infoFac->appendChild($xml->createElement('totalSinImpuestos', $totalSinImpuestos));
        $infoFac->appendChild($xml->createElement('totalDescuento', $totalDescuento));

        $tci = $xml->createElement('totalConImpuestos');
        $infoFac->appendChild($tci);

        foreach ($totalesIva as $codigoPorcentaje => $t) {
            $totalImp = $xml->createElement('totalImpuesto');
            $tci->appendChild($totalImp);

            $totalImp->appendChild($xml->createElement('codigo', '2'));
            $totalImp->appendChild($xml->createElement('codigoPorcentaje', (string)$codigoPorcentaje));
            $totalImp->appendChild($xml->createElement('baseImponible', number_format((float)$t['base'], 2, '.', '')));
            $totalImp->appendChild($xml->createElement('tarifa', $codigoPorcentaje === '0' ? '0.00' : $t['tarifa']));
            $totalImp->appendChild($xml->createElement('valor', number_format((float)$t['valor'], 2, '.', '')));
        }

        $infoFac->appendChild($xml->createElement('propina', '0.00'));
        $infoFac->appendChild($xml->createElement('importeTotal', $importeTotal));
        $infoFac->appendChild($xml->createElement('moneda', 'DOLAR'));

        $pagos = $xml->createElement('pagos');

        $payments = $sale->payments ?? collect();
        if ($payments instanceof \Illuminate\Database\Eloquent\Collection === false) {
            $payments = collect($payments);
        }

        if ($payments->isEmpty()) {
            $pago  = $xml->createElement('pago');
            $pago->appendChild($xml->createElement('formaPago', '01'));
            $pago->appendChild($xml->createElement('total', $importeTotal));
            $pago->appendChild($xml->createElement('plazo', '0'));
            $pago->appendChild($xml->createElement('unidadTiempo', 'dias'));
            $pagos->appendChild($pago);
        } else {
            $sum = 0.0;

            foreach ($payments as $p) {
                $codigoSri = $p->paymentMethod?->codigo_sri
                    ? (string) $p->paymentMethod->codigo_sri
                    : $this->mapFormaPagoSri((string) ($p->metodo ?? ''));

                $montoPago = round((float) ($p->monto ?? 0), 2);
                if ($montoPago <= 0) $montoPago = round((float) $importeTotal, 2);

                $sum += $montoPago;

                $pago = $xml->createElement('pago');
                $pago->appendChild($xml->createElement('formaPago', $codigoSri));
                $pago->appendChild($xml->createElement('total', number_format($montoPago, 2, '.', '')));
                $pago->appendChild($xml->createElement('plazo', '0'));
                $pago->appendChild($xml->createElement('unidadTiempo', 'dias'));
                $pagos->appendChild($pago);
            }

            $importe = round((float) $importeTotal, 2);
            $diff = round($importe - $sum, 2);

            if (abs($diff) >= 0.01 && $pagos->lastChild) {
                foreach ($pagos->lastChild->childNodes as $node) {
                    if ($node->nodeName === 'total') {
                        $nuevo = round(((float) $node->nodeValue) + $diff, 2);
                        $node->nodeValue = number_format($nuevo, 2, '.', '');
                        break;
                    }
                }
            }
        }

        $infoFac->appendChild($pagos);



  
        $detalles = $xml->createElement('detalles');
        $factura->appendChild($detalles);

        foreach ($sale->items as $it) {
            $det = $xml->createElement('detalle');
            $detalles->appendChild($det);

            $det->appendChild($xml->createElement('codigoPrincipal', (string)($it->producto_id)));
            $det->appendChild($xml->createElement('descripcion', (string)$it->descripcion));
            $det->appendChild($xml->createElement('cantidad', number_format((float)$it->cantidad, 2, '.', '')));
            $det->appendChild($xml->createElement('precioUnitario', number_format((float)$it->precio_unitario, 2, '.', '')));
            $det->appendChild($xml->createElement('descuento', number_format((float)($it->descuento ?? 0), 2, '.', '')));
            $det->appendChild($xml->createElement('precioTotalSinImpuesto', number_format((float)$it->total, 2, '.', '')));

            $imps = $xml->createElement('impuestos');
            $det->appendChild($imps);

            $imp = $xml->createElement('impuesto');
            $imps->appendChild($imp);

            $pctLinea = round((float) ($it->iva_porcentaje ?? 0), 2);
            $codigoPorcentajeLinea = $this->sriCodigoPorcentajeIva($pctLinea);
            $tarifaLinea = $codigoPorcentajeLinea === '0' ? '0.00' : number_format($pctLinea, 2, '.', '');

            $baseLinea = round((float) $it->total, 2);
            $ivaLinea  = round($baseLinea * ($pctLinea / 100), 2);

            $imp->appendChild($xml->createElement('codigo', '2'));
            $imp->appendChild($xml->createElement('codigoPorcentaje', $codigoPorcentajeLinea));
            $imp->appendChild($xml->createElement('tarifa', $tarifaLinea));
            $imp->appendChild($xml->createElement('baseImponible', number_format($baseLinea, 2, '.', '')));
            $imp->appendChild($xml->createElement('valor', number_format($ivaLinea, 2, '.', '')));
        }

        return $xml->saveXML();
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

            $certPath = $this->resolveCertPath((string) env('SRI_CERT_PATH'));
            $certPass = trim((string) env('SRI_CERT_PASSWORD', ''));

            if ($certPass === '') {
                throw ValidationException::withMessages([
                    'sri' => 'Falta SRI_CERT_PASSWORD en .env.',
                ]);
            }

            if (!is_file($certPath)) {
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
        if ($envPath === '') return storage_path('app/sri/certs/certificado.p12');

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
            $id  = $ref->getAttribute('Id');
            $uri = $ref->getAttribute('URI');

            if ($id === $signedPropsRefId || $uri === "#{$signedPropsId}") {
                $ref->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
                break;
            }
        }

        $dsig->sign($key);

        $dsig->sigNode->setAttribute('Id', $signatureId);

        $dsig->add509Cert($publicCert, true, false, ['subjectName' => false]);

        $dsig->appendSignature($root);

        return $doc->saveXML();

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
        if (!$issuer) return '';
        $order = ['C','ST','L','O','OU','CN','emailAddress'];
        $parts = [];

        foreach ($order as $k) {
            if (isset($issuer[$k])) {
                $v = $issuer[$k];
                $parts[] = $k . '=' . $v;
            }
        }

        foreach ($issuer as $k => $v) {
            if (in_array($k, $order, true)) continue;
            $parts[] = $k . '=' . $v;
        }

        return implode(',', $parts);
    }

    private function sriCodigoPorcentajeIva(float $pct): string
    {
        if ($pct <= 0) return '0';
        if (abs($pct - 12.0) < 0.01) return '2';
        if (abs($pct - 14.0) < 0.01) return '3';
        if (abs($pct - 15.0) < 0.01) return '4';
        return '4';
    }

    private function mapFormaPagoSri(string $metodo): string
    {
        $m = strtoupper(trim($metodo));

        if (str_contains($m, 'TRANSFER')) return '17';
        if (str_contains($m, 'CRÉDITO') || str_contains($m, 'CREDITO')) return '19';
        if (str_contains($m, 'DÉBITO') || str_contains($m, 'DEBITO')) return '16';

        return '01'; 
    }


}
