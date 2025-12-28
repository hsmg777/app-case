{{-- resources/views/sri/ride/factura.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>RIDE - Factura</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { padding: 0; vertical-align: top; }

        .box{
            border: 1px solid #333;
            border-radius: 7px;
            box-sizing: border-box;
        }

        .pad-10 { padding: 10px; box-sizing: border-box; }
        .muted { color: #555; }
        .right { text-align: right; }
        .center { text-align: center; }

        .title { font-size: 13px; font-weight: bold; }
        .small { font-size: 10px; }
        .xs { font-size: 9px; }

        .mb-10 { margin-bottom: 10px; }
        .mb-14 { margin-bottom: 14px; }

        .logo-img{
            width: 190px;
            height: auto;
            max-height: 70px;
            display: block;
            margin: 0 auto;
        }

        .t-border td, .t-border th { border: 1px solid #333; padding: 6px; }
        .t-head th { background: #f2f2f2; font-weight: bold; }

        /* ===== CABECERA (MISMA ALTURA REAL) ===== */
        .header-wrap { width: 100%; margin-bottom: 14px; }
        .header-left, .header-right { width: 50%; }

        .left-inner { width: 100%; height: 285px; }
        .left-logo-cell { height: 80px; vertical-align: top; overflow: hidden; }
        .left-box-cell  { height: 205px; vertical-align: top; }

        .right-box { height: 285px; box-sizing: border-box; }

        .info-table td { padding: 3px 0; vertical-align: top; }
        .label { width: 45%; }

        .barcode-wrap { margin-top: 10px; }
        .barcode-img { width: 100%; height: 48px; }
        .barcode-text { margin-top: 3px; font-size: 9px; text-align: center; word-break: break-all; }
    </style>
</head>
<body>
@php
    $cfg = $cfg ?? null;

    $claveAcceso = (string)($invoice->clave_acceso ?? '');
    $numAut = (string)($invoice->numero_autorizacion ?? '');
    $fechaAut = $invoice->fecha_autorizacion ?? null;

    $ambiente = ((int)($cfg->ambiente ?? 1) === 2) ? 'PRODUCCIÓN' : 'PRUEBAS';
    $emision = 'NORMAL';

    $rucEmisor = preg_replace('/\D+/', '', (string)($cfg->ruc ?? ''));
    $razonSocial = (string)($cfg->razon_social ?? 'EMISOR');
    $nombreComercial = (string)($cfg->nombre_comercial ?? $razonSocial);
    $dirMatriz = (string)($cfg->direccion_matriz ?? 'S/D');
    $dirSucursal = (string)($cfg->direccion_establecimiento ?? $dirMatriz);
    $obligado = ($cfg && $cfg->obligado_contabilidad) ? 'SI' : 'NO';

    $clienteNombre = (string)($sale->client->business ?? $sale->client->nombre ?? 'CONSUMIDOR FINAL');
    $clienteId = (string)($sale->client->identificacion ?? '9999999999999');
    $fechaEmision = \Carbon\Carbon::parse($sale->fecha_venta)->format('d/m/Y');
    $clientDirection = (string)($sale->client->direccion ?? 'Quito');


    $items = $sale->items ?? collect();

    $subtotal0 = 0.0;
    $subtotalIva = [];
    $ivaTotal = 0.0;
    $subtotalSinImpuestos = 0.0;
    $descuentoTotal = 0.0;

    foreach ($items as $it) {
        $base = round((float)($it->total ?? 0), 2);
        $pct  = round((float)($it->iva_porcentaje ?? 0), 2);
        $desc = round((float)($it->descuento ?? 0), 2);

        $subtotalSinImpuestos += $base;
        $descuentoTotal += $desc;

        if ($pct <= 0) {
            $subtotal0 += $base;
        } else {
            if (!isset($subtotalIva[(string)$pct])) $subtotalIva[(string)$pct] = 0.0;
            $subtotalIva[(string)$pct] += $base;
            $ivaTotal += round($base * ($pct / 100), 2);
        }
    }

    $importeTotal = round($subtotalSinImpuestos + $ivaTotal, 2);

    $payments = $sale->payments ?? collect();
    if ($payments instanceof \Illuminate\Database\Eloquent\Collection === false) {
        $payments = collect($payments);
    }

    $formasPago = [];
    foreach ($payments as $p) {
        $codigoSri = $p->paymentMethod?->codigo_sri ? (string)$p->paymentMethod->codigo_sri : '01';
        $nombreMetodo = $p->paymentMethod?->nombre ?? ($p->metodo ?? 'PAGO');
        $monto = round((float)($p->monto ?? 0), 2);
        if ($monto <= 0) $monto = $importeTotal;

        $formasPago[] = [
            'codigo' => $codigoSri,
            'nombre' => $nombreMetodo,
            'monto'  => $monto,
        ];
    }

    if (empty($formasPago)) {
        $formasPago[] = ['codigo' => '01', 'nombre' => 'EFECTIVO', 'monto' => $importeTotal];
    }

    // Logo en public/images/logo.png
    $logoPath = public_path('images/logo.png');
    $logoSrc  = is_file($logoPath) ? 'file://' . $logoPath : null;

    // Barcode (CODE128) embebido en base64
    $barcodeSrc = null;
    if ($claveAcceso !== '') {
        try {
            $gen = new \Picqer\Barcode\BarcodeGeneratorPNG();
            $png = $gen->getBarcode($claveAcceso, $gen::TYPE_CODE_128, 2, 60);
            $barcodeSrc = 'data:image/png;base64,' . base64_encode($png);
        } catch (\Throwable $e) {
            $barcodeSrc = null;
        }
    }
@endphp

{{-- =========================
    CABECERA (2 BLOQUES MISMA ALTURA)
========================= --}}
<table class="header-wrap">
    <tr>
        {{-- LEFT --}}
        <td class="header-left" style="padding-right: 8px;">
            <table class="left-inner" style="height:285px;">
                <tr>
                    <td class="left-logo-cell center">
                        @if($logoSrc)
                            <img src="{{ $logoSrc }}" class="logo-img" alt="Logo">
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="left-box-cell">
                        <div class="box pad-10" style="height:205px;">
                            <div class="title">{{ $razonSocial }}</div>
                            <div class="small">{{ $nombreComercial }}</div>
                            <div style="height: 8px;"></div>

                            <table class="info-table" style="width:100%;">
                                <tr>
                                    <td class="xs muted" style="width: 40%;">Dirección Matriz:</td>
                                    <td class="small">{{ $dirMatriz }}</td>
                                </tr>
                                <tr>
                                    <td class="xs muted">Dirección Sucursal:</td>
                                    <td class="small">{{ $dirSucursal }}</td>
                                </tr>
                                <tr>
                                    <td class="xs muted">OBLIGADO A LLEVAR CONTABILIDAD</td>
                                    <td class="small">{{ $obligado }}</td>
                                </tr>
                                @if(!empty($cfg->regimen) || !empty($cfg->contribuyente_especial) || !empty($cfg->rimpe))
                                    <tr>
                                        <td class="xs muted">CONTRIBUYENTE:</td>
                                        <td class="small">
                                            {{ (string)($cfg->rimpe ?? $cfg->regimen ?? $cfg->contribuyente_especial ?? '') }}
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </td>

        {{-- RIGHT --}}
        <td class="header-right" style="padding-left: 8px;">
            <div class="box pad-10 right-box">
                <table class="info-table" style="width:100%;">
                    <tr>
                        <td class="xs muted label">R.U.C.:</td>
                        <td class="small">{{ $rucEmisor }}</td>
                    </tr>
                    <tr>
                        <td class="xs muted">FACTURA</td>
                        <td class="small">No. {{ $sale->num_factura }}</td>
                    </tr>

                    <tr>
                        <td class="xs muted" colspan="2">NÚMERO DE AUTORIZACIÓN</td>
                    </tr>
                    <tr>
                        <td class="small" colspan="2" style="font-size:10px">
                            {{ $numAut }}
                        </td>
                    </tr>

                    <tr>
                        <td class="xs muted">FECHA Y HORA DE AUTORIZACIÓN:</td>
                        <td class="small">{{ $fechaAut ? \Carbon\Carbon::parse($fechaAut)->format('d/m/Y H:i:s') : '' }}</td>
                    </tr>
                    <tr>
                        <td class="xs muted">AMBIENTE:</td>
                        <td class="small">{{ $ambiente }}</td>
                    </tr>
                    <tr>
                        <td class="xs muted">EMISIÓN:</td>
                        <td class="small">{{ $emision }}</td>
                    </tr>
                    <tr>
                        <td class="xs muted" style="padding-top: 8px;" colspan="2">CLAVE DE ACCESO</td>
                    </tr>
                </table>

                <div class="barcode-wrap">
                    @if($barcodeSrc)
                        <img src="{{ $barcodeSrc }}" class="barcode-img" alt="Barcode">
                        <div class="barcode-text">{{ $claveAcceso }}</div>
                    @else
                        <div class="small" style="word-break: break-all;"><b>{{ $claveAcceso }}</b></div>
                    @endif
                </div>
            </div>
        </td>
    </tr>
</table>

{{-- =========================
    DATOS CLIENTE
========================= --}}
<div class="box pad-10 mb-14">
    <table style="width:100%;">
        <tr>
            <td style="width: 36%;">
                <span class="xs muted">Razón Social / Nombres y Apellidos:</span><br>
                <b>{{ $clienteNombre }}</b>
            </td>
            <td style="width: 32%;">
                <span class="xs muted">Identificación</span><br>
                <b>{{ $clienteId }}</b>
            </td>
            <td style="width: 32%;">
                <span class="xs muted">Fecha Emisión</span><br>
                <b>{{ $fechaEmision }}</b>
            </td>
            <td style="width: 32%;">
                <span class="xs muted">Dirección</span><br>
                <b>{{ $clientDirection }}</b>
            </td>
        </tr>
    </table>
</div>

{{-- =========================
    DETALLE
========================= --}}
<table class="t-border t-head mb-14">
    <thead>
        <tr>
            <th style="width: 10%;">Cod.<br>Principal</th>
            <th style="width: 10%;">Cantidad</th>
            <th style="width: 40%;">Descripción</th>
            <th style="width: 13%;" class="right">Precio Unitario</th>
            <th style="width: 12%;" class="right">Descuento</th>
            <th style="width: 15%;" class="right">Precio Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $it)
            @php
                $cant = number_format((float)$it->cantidad, 2, '.', '');
                $pu   = number_format((float)$it->precio_unitario, 2, '.', '');
                $desc = number_format((float)($it->descuento ?? 0), 2, '.', '');
                $totalLinea = number_format((float)$it->total, 2, '.', '');
            @endphp
            <tr>
                <td class="small">{{ $it->producto_id }}</td>
                <td class="right small">{{ $cant }}</td>
                <td class="small">{{ $it->descripcion }}</td>
                <td class="right small">{{ $pu }}</td>
                <td class="right small">{{ $desc }}</td>
                <td class="right small">{{ $totalLinea }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table>
    <tr>
        <td style="width: 45%; padding-right: 8px;">
            <table class="t-border">
                <thead class="t-head">
                    <tr>
                        <th>Forma de pago</th>
                        <th class="right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($formasPago as $fp)
                        <tr>
                            <td class="small">{{ $fp['codigo'] }} - {{ $fp['nombre'] }}</td>
                            <td class="right small">{{ number_format((float)$fp['monto'], 2, '.', '') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </td>

        <td style="width: 55%; padding-left: 8px;">
            <table class="t-border">
                <tbody>
                    @foreach($subtotalIva as $tasa => $base)
                        <tr>
                            <td><b>SUBTOTAL {{ rtrim(rtrim(number_format((float)$tasa, 2, '.', ''), '0'), '.') }}%</b></td>
                            <td class="right">{{ number_format((float)$base, 2, '.', '') }}</td>
                        </tr>
                    @endforeach

                    <tr>
                        <td><b>SUBTOTAL 0%</b></td>
                        <td class="right">{{ number_format((float)$subtotal0, 2, '.', '') }}</td>
                    </tr>
                    <tr>
                        <td><b>SUBTOTAL SIN IMPUESTOS</b></td>
                        <td class="right">{{ number_format((float)$subtotalSinImpuestos, 2, '.', '') }}</td>
                    </tr>
                    <tr>
                        <td><b>TOTAL DESCUENTO</b></td>
                        <td class="right">{{ number_format((float)$descuentoTotal, 2, '.', '') }}</td>
                    </tr>

                    @foreach($subtotalIva as $tasa => $base)
                        <tr>
                            <td><b>IVA {{ rtrim(rtrim(number_format((float)$tasa, 2, '.', ''), '0'), '.') }}%</b></td>
                            <td class="right">
                                {{ number_format((float)round($base * ((float)$tasa/100), 2), 2, '.', '') }}
                            </td>
                        </tr>
                    @endforeach

                    <tr>
                        <td><b>VALOR TOTAL</b></td>
                        <td class="right"><b>{{ number_format((float)$importeTotal, 2, '.', '') }}</b></td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
