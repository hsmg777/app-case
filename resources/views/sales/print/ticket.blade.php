<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Ticket {{ $sale->num_factura ?? $sale->id }}</title>

  <style>
    @page { size: 80mm auto; margin: 0; }
    html, body { margin:0; padding:0; }
    body { width: 80mm; font-family: Arial, sans-serif; font-size: 12px; color:#000; }
    .wrap { padding: 10px 8px; }
    .center { text-align:center; }
    .bold { font-weight: 700; }
    .hr { border-top: 1px dashed #000; margin: 8px 0; }
    .row { display:flex; justify-content:space-between; gap:10px; }
    .small { font-size: 10px; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding: 3px 0; vertical-align:top; }
    th { font-size:10px; text-transform:uppercase; border-bottom: 1px solid #000; }
    .right { text-align:right; }
    .muted { color:#111; opacity:.9; }
    .note { font-size:10px; line-height:1.35; }
    @media print { .no-print { display:none !important; } }
  </style>
</head>
<body>
@php
  $clientNombre = $sale->client->nombre ?? $sale->cliente_nombre ?? 'Consumidor final';
  $clientIdent  = $sale->client->identificacion ?? $sale->cliente_identificacion ?? '9999999999';
  $clientTel    = $sale->client->telefono ?? $sale->cliente_telefono ?? '-';
  $clientDir    = $sale->client->direccion ?? $sale->cliente_direccion ?? '-';

  $clientEmail  =
      $sale->email_destino
      ?? ($sale->clientEmail->email ?? null)
      ?? ($sale->client->email ?? null)
      ?? ($sale->cliente_email ?? null);

  $atendidoPor  = $sale->user->name ?? $sale->user->nombre ?? '-';
  $invoice = \App\Models\Sri\ElectronicInvoice::where('sale_id', $sale->id)->first();
  $claveAcceso = $invoice?->clave_acceso;
@endphp


  <div class="wrap">
    <div class="center bold" style="font-size:14px;">Papeleria y Bazar</div>
    <div class="center bold" style="font-size:20px;">"El estudiante"</div>

    <div class="center" style="font-size:12px;">Calle, José Miguel Guarderas S/N</div>
    <div class="center" style="font-size:12px;">Calderon, 170203</div>
    <div class="center" style="font-size:12px;">Teléfono: 099 982 6100</div>
    <div class="center" style="font-size:12px;">SIMBAÑA GALARZA JOSE SALOMON</div>
    <div class="center" style="font-size:12px;">RUC: 1710177245001</div>
    <div class="center" style="font-size:12px;">Correo: facturas@papeleriaybazarelestudiante.com</div>

    <div class="hr"></div>

    <div>
      <div class="center" style="font-size:14px;">Factura Electronica N°</div>
      <div  class="center" style="font-size:14px;">{{ $sale->num_factura ?? ('#'.$sale->id) }}</div>
      <div class="center" style="font-size:10px;">Ambiente: PRUEBAS</div>
    </div>

    <div class="hr"></div>

    <div class="row small">
      <div>Fecha</div>
      <div class="right">{{ \Carbon\Carbon::parse($sale->fecha_venta)->format('d/m/Y H:i') }}</div>
    </div>

    <div class="row small">
      <div>Cliente</div>
      <div class="right">{{ $clientNombre }}</div>
    </div>

    <div class="row small">
      <div>Identificación</div>
      <div class="right">{{ $clientIdent }}</div>
    </div>

    <div class="row small">
      <div>Teléfono</div>
      <div class="right">{{ $clientTel }}</div>
    </div>

    <div class="row small">
      <div>Dirección</div>
      <div class="right">{{ $clientDir }}</div>
    </div>

    @if($clientEmail)
      <div class="row small">
        <div>Correo</div>
        <div class="right">{{ $clientEmail }}</div>
      </div>
    @endif

    <div class="hr"></div>

    <table>
      <thead>
        <tr>
          <th>Producto</th>
          <th class="right">Cant</th>
          <th class="right">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sale->items as $it)
          @php
            $ivaPctItem = $it->iva_porcentaje ?? null;
            $ivaPctProd = $it->product->iva_porcentaje ?? null;
            $ivaPct     = is_numeric($ivaPctItem) ? (float)$ivaPctItem : (is_numeric($ivaPctProd) ? (float)$ivaPctProd : 0);
            $gravaIva   = $ivaPct > 0;
          @endphp
          <tr>
            <td>
              {{ $it->descripcion }}@if($gravaIva) * @endif
              <div class="small muted">
                ${{ number_format($it->precio_unitario, 2) }}
                @if(($it->descuento ?? 0) > 0)
                  - Desc ${{ number_format($it->descuento, 2) }}
                @endif
              </div>
            </td>
            <td class="right">{{ $it->cantidad }}</td>
            <td class="right">${{ number_format($it->total, 2) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="small muted" style="margin-top:6px;">
      *
    </div>

    <div class="hr"></div>

    <div class="row"><div>Subtotal</div><div class="right">${{ number_format($sale->subtotal, 2) }}</div></div>
    <div class="row"><div>Descuento</div><div class="right">${{ number_format($sale->descuento, 2) }}</div></div>
    <div class="row"><div>IVA</div><div class="right">${{ number_format($sale->iva, 2) }}</div></div>
    <div class="row bold" style="font-size:14px;"><div>TOTAL</div><div class="right">${{ number_format($sale->total, 2) }}</div></div>

    @php $p = $sale->payments->first(); @endphp
    <div class="hr"></div>
    <div class="row small"><div>Método de pago</div><div class="right">{{ $p->metodo ?? '-' }}</div></div>
    <div class="row small"><div>Recibido</div><div class="right">${{ number_format($p->monto_recibido ?? 0, 2) }}</div></div>
    <div class="row small"><div>Cambio</div><div class="right">${{ number_format($p->cambio ?? 0, 2) }}</div></div>
    <div class="row small"> <div>Atendido por</div> <div class="right">{{ $atendidoPor }}</div></div>
    <div class="hr"></div>

    {{-- Texto comprobante electrónico --}}
    <div class="note">
      <div class="bold">Comprobante electrónico</div>
      <div>
        Su comprobante electrónico ha sido generado correctamente y ha sido enviado al correo:
        <span class="bold">{{ $clientEmail ?? 'N/D' }}</span>.
      </div>
      <div style="margin-top:6px;">
        Recuerde también que puede consultar su comprobante en el portal del SRI:
        srienlinea.sri.gob.ec
      </div>
      <div style="margin-top:6px;">
        Dentro de las próximas 24h con la siguiente clave de acceso:
        <span class="bold">{{ $claveAcceso ?? 'PENDIENTE' }}</span>
      </div>
    </div>

    <div class="hr"></div>
    <div class="center bold">¡Gracias por su compra!</div>
    <div class="hr"></div>
    <div class="center bold" style="font-size:10px;">Desarrollado por Nivusoftware</div>

    <div class="no-print" style="margin-top:10px;">
      <button onclick="window.print()">Imprimir</button>
      <button onclick="window.close()">Cerrar</button>
    </div>
  </div>

  @if($auto)
  <script>
    window.addEventListener('load', () => {
      setTimeout(() => {
        window.focus();
        window.print();
      }, 200);
    });

    window.addEventListener('afterprint', () => {
      const qs = new URLSearchParams(location.search);
      if (qs.get('embed') === '1' && window.parent) {
        window.parent.postMessage({ type: 'ticket-printed', id: {{ $sale->id }} }, '*');
      }
    });
  </script>
  @endif

</body>
</html>
