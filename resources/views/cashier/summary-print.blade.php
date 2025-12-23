<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resumen Caja #{{ $session->caja_id }}</title>

    <style>
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }

        body {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
            margin: 0;
            color: #000;
        }

        .center { text-align: center; }
        .muted { color: #333; }
        .hr { border-top: 1px dashed #000; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .bold { font-weight: 800; }
        .big { font-size: 15px; font-weight: 900; }
        .totals {
            border: 1px solid #000;
            padding: 6px;
            margin-top: 8px;
        }
        .tbl {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .tbl th, .tbl td {
            padding: 3px 0;
            vertical-align: top;
        }
        .right { text-align: right; }
        .small { font-size: 11px; }

        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>

<body>
    @php
        $resultLabel = $session->result === 'MATCH'
            ? 'CUADRA'
            : ($session->result === 'SHORT'
                ? 'FALTANTE'
                : ($session->result === 'OVER'
                    ? 'SOBRANTE'
                    : $session->result
                )
            );
    @endphp

    <div class="center bold">RESUMEN DE CAJA #{{ $session->caja_id }}</div>
    <div class="center small muted">CASEAPP$</div>

    <div class="hr"></div>

    <div class="row"><span class="muted">Apertura:</span><span>{{ $session->opened_at?->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span class="muted">Por:</span><span>{{ $session->opener?->name }}</span></div>
    <div class="row"><span class="muted">Monto apertura:</span><span class="bold">${{ number_format((float)$session->opening_amount, 2) }}</span></div>

    <div class="hr"></div>

    <div class="row"><span class="muted">Cierre:</span><span>{{ $session->closed_at?->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span class="muted">Por:</span><span>{{ $session->closer?->name }}</span></div>
    <div class="row"><span class="muted">Resultado:</span><span class="bold">{{ $resultLabel }}</span></div>

    <div class="totals">
        <div class="row">
            <span class="bold">TOTAL ESPERADO</span>
            <span class="big">${{ number_format((float)$session->expected_amount, 2) }}</span>
        </div>
        <div class="row">
            <span class="bold">TOTAL DECLARADO</span>
            <span class="big">${{ number_format((float)$session->declared_amount, 2) }}</span>
        </div>
        <div class="row">
            <span class="bold">DIFERENCIA</span>
            <span class="big">${{ number_format((float)$session->difference_amount, 2) }}</span>
        </div>
    </div>

    <div class="hr"></div>
    <div class="bold">MOVIMIENTOS (INGRESOS / RETIROS)</div>

    <table class="tbl">
        <thead>
            <tr class="small">
                <th>Hora</th>
                <th>Tipo</th>
                <th class="right">Monto</th>
            </tr>
        </thead>
        <tbody>
        @forelse($session->movements as $m)
            @php
                $tipoLabel = $m->type === 'IN'
                    ? 'Ingreso'
                    : ($m->type === 'OUT' ? 'Retiro' : $m->type);
            @endphp

            <tr>
                <td class="small">{{ $m->created_at?->format('H:i') }}</td>
                <td class="small bold">{{ $tipoLabel }}</td>
                <td class="small right">${{ number_format((float)$m->amount, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3" class="small muted">{{ $m->reason }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="small muted center">Sin movimientos.</td></tr>
        @endforelse
        </tbody>
    </table>

    @if($session->notes)
        <div class="hr"></div>
        <div class="bold">NOTAS</div>
        <div class="small">{{ $session->notes }}</div>
    @endif

    <div class="hr"></div>

    <script>
        window.addEventListener('load', () => {
            window.print();
            setTimeout(() => {
                window.parent?.postMessage({ type: 'cash-summary-printed' }, '*');
            }, 300);
        });
    </script>
</body>
</html>
