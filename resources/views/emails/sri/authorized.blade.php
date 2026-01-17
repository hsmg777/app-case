<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Factura electrónica autorizada</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;">
    {{-- Wrapper --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f4f6f8;">
        <tr>
            <td align="center" style="padding:24px 12px;">

                {{-- Container --}}
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:640px;max-width:100%;background:#ffffff;border:1px solid #e7ebf0;border-radius:12px;overflow:hidden;">
                    
                    {{-- Header --}}
                    <tr>
                        <td style="background:#0f2a5f;padding:18px 20px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left" style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;">
                                        <div style="font-size:14px;opacity:.9;">Factura electrónica</div>
                                        <div style="font-size:20px;font-weight:700;line-height:1.2;margin-top:2px;">
                                            Autorizada por el SRI
                                        </div>
                                    </td>
                                    <td align="right" style="font-family:Arial,Helvetica,sans-serif;color:#ffffff;">
                                        <div style="font-size:12px;opacity:.9;">N° Factura</div>
                                        <div style="font-size:16px;font-weight:700;">
                                            {{ $sale->num_factura ?? 'S/N' }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:22px 20px;font-family:Arial,Helvetica,sans-serif;color:#111;">

                            {{-- Intro --}}
                            <div style="font-size:14px;line-height:1.6;margin:0 0 14px;">
                                Hola{{ $sale?->client?->business ? ' ' . e($sale->client->business) : '' }},
                                <br>
                                Adjuntamos su <b>factura electrónica autorizada</b> por el SRI.
                            </div>

                            {{-- Card --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e7ebf0;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:14px 14px;background:#f8fafc;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="font-size:13px;color:#334155;">
                                                    Resumen de comprobante
                                                </td>
                                                <td align="right">
                                                    @php
                                                        $estado = strtoupper((string)($invoice->estado_sri ?? ''));
                                                        $badgeBg = $estado === 'AUTORIZADO' ? '#e7f7ee' : '#fff4e5';
                                                        $badgeTx = $estado === 'AUTORIZADO' ? '#0f7a3a' : '#9a5b00';
                                                        $badgeBd = $estado === 'AUTORIZADO' ? '#bfe8cf' : '#ffd8a8';
                                                    @endphp
                                                    <span style="display:inline-block;padding:6px 10px;border-radius:999px;background:{{ $badgeBg }};color:{{ $badgeTx }};border:1px solid {{ $badgeBd }};font-size:12px;font-weight:700;">
                                                        {{ $invoice->estado_sri ?? '—' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 14px 14px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                            @php
                                                $fecha = $sale->fecha_venta
                                                    ? \Carbon\Carbon::parse($sale->fecha_venta)->timezone(config('app.timezone', 'America/Guayaquil'))->format('d/m/Y H:i')
                                                    : '—';

                                                $total = number_format((float)($sale->total ?? 0), 2, '.', '');
                                                $clave = (string)($invoice->clave_acceso ?? 'S/N');
                                                $clienteId = (string)($sale?->client?->identificacion ?? '');
                                            @endphp

                                            <tr>
                                                <td style="padding:10px 0;border-bottom:1px solid #eef2f6;width:38%;color:#64748b;font-size:12px;">
                                                    Cliente
                                                </td>
                                                <td style="padding:10px 0;border-bottom:1px solid #eef2f6;color:#0f172a;font-size:13px;font-weight:700;">
                                                    {{ $sale?->client?->business ?? $sale?->client?->nombre ?? 'CONSUMIDOR FINAL' }}
                                                    @if($clienteId !== '')
                                                        <span style="font-weight:400;color:#475569;"> ({{ $clienteId }})</span>
                                                    @endif
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style="padding:10px 0;border-bottom:1px solid #eef2f6;color:#64748b;font-size:12px;">
                                                    Fecha de emisión
                                                </td>
                                                <td style="padding:10px 0;border-bottom:1px solid #eef2f6;color:#0f172a;font-size:13px;">
                                                    {{ $fecha }}
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style="padding:10px 0;border-bottom:1px solid #eef2f6;color:#64748b;font-size:12px;">
                                                    Total
                                                </td>
                                                <td style="padding:10px 0;border-bottom:1px solid #eef2f6;color:#0f172a;font-size:16px;font-weight:800;">
                                                    ${{ $total }}
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style="padding:10px 0;color:#64748b;font-size:12px;vertical-align:top;">
                                                    Clave de acceso
                                                </td>
                                                <td style="padding:10px 0;color:#0f172a;font-size:12px;word-break:break-all;font-family:Consolas,Monaco,monospace;">
                                                    {{ $clave }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Attachments note --}}
                            <div style="margin:14px 0 0;font-size:13px;color:#334155;line-height:1.6;">
                                Adjuntos:
                                <b>RIDE.pdf</b> y <b>FACTURA.xml</b>.
                                <br>
                                Si necesitas ayuda, responde a este correo o contáctanos por nuestros canales oficiales.
                            </div>

                            {{-- Divider --}}
                            <div style="height:1px;background:#eef2f6;margin:18px 0;"></div>

                            {{-- Footer --}}
                            <div style="font-size:12px;color:#64748b;line-height:1.6;">
                                Este es un mensaje automático. Por favor no responder a este correo.
                                <br>
                                <span style="color:#94a3b8;">© {{ date('Y') }} P&B El Estudiante</span>
                            </div>

                        </td>
                    </tr>

                </table>
                {{-- /Container --}}

                {{-- Small spacer --}}
                <div style="height:14px;line-height:14px;">&nbsp;</div>

            </td>
        </tr>
    </table>
</body>
</html>
