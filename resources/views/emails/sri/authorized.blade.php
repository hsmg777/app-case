<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura electrónica autorizada</title>
</head>
<body style="font-family: Arial, sans-serif; color:#111; line-height:1.5;">
    <h2 style="margin:0 0 10px;">Factura electrónica autorizada ✅</h2>

    <p style="margin:0 0 12px;">
        Hola, se adjunta la factura electrónica autorizada por el SRI.
    </p>

    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse; width:100%; max-width:640px;">
        <tr>
            <td style="border:1px solid #ddd; background:#f7f7f7; width:220px;"><strong>N° Factura</strong></td>
            <td style="border:1px solid #ddd;">{{ $sale->num_factura ?? 'S/N' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #ddd; background:#f7f7f7;"><strong>Clave de acceso</strong></td>
            <td style="border:1px solid #ddd;">{{ $invoice->clave_acceso ?? 'S/N' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #ddd; background:#f7f7f7;"><strong>Fecha</strong></td>
            <td style="border:1px solid #ddd;">
                {{ optional($sale->fecha_venta)->format('Y-m-d H:i') ?? $sale->fecha_venta }}
            </td>
        </tr>
        <tr>
            <td style="border:1px solid #ddd; background:#f7f7f7;"><strong>Total</strong></td>
            <td style="border:1px solid #ddd;">${{ number_format((float)($sale->total ?? 0), 2, '.', '') }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #ddd; background:#f7f7f7;"><strong>Estado SRI</strong></td>
            <td style="border:1px solid #ddd;">{{ $invoice->estado_sri ?? '' }}</td>
        </tr>
    </table>

    <p style="margin:14px 0 0;">
        Adjuntos: <strong>RIDE.pdf</strong> y <strong>FACTURA.xml</strong>
    </p>

    <p style="margin:18px 0 0; font-size:12px; color:#555;">
        Este es un mensaje automático, por favor no responder.
    </p>
</body>
</html>
