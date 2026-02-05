<?php

namespace App\Services\Reporting;

use App\Models\Sri\ElectronicInvoice;
use App\Repositories\Reporting\ReportingRepository;
use App\Services\Store\BodegaService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class ReportingService
{
    public function __construct(
        private ReportingRepository $reporting,
        private BodegaService $bodegas
    )
    {
    }

    public function getInvoiceStatuses(Request $request): array
    {
        $estado = strtoupper(trim((string) $request->query('estado', '')));
        $q = trim((string) $request->query('q', ''));

        $maxReviewHours = (int) config('sri.max_review_hours', 72);

        $invoices = $this->reporting->getInvoiceStatuses($estado, $q, $maxReviewHours);

        $now = now();

        $invoices->getCollection()->transform(function (ElectronicInvoice $inv) use ($now, $maxReviewHours) {
            $estadoInv = strtoupper((string) ($inv->estado_sri ?? ''));

            $inv->pendiente_revision = false;
            if ($estadoInv !== 'AUTORIZADO' && $estadoInv !== 'RECHAZADO' && $inv->updated_at) {
                $inv->pendiente_revision = $inv->updated_at->diffInHours($now) >= $maxReviewHours;
            }

            if ($estadoInv === 'AUTORIZADO' || !$inv->updated_at) {
                $inv->dias_pendiente = null;
                $inv->pendiente_texto = null;
                return $inv;
            }

            $minutes = (int) $inv->updated_at->diffInMinutes($now);
            if ($minutes >= 1440) {
                $days = intdiv($minutes, 1440);
                $inv->pendiente_texto = "hace {$days}d";
                $inv->dias_pendiente = $days;
                return $inv;
            }

            if ($minutes >= 60) {
                $hours = intdiv($minutes, 60);
                $inv->pendiente_texto = "hace {$hours}h";
                $inv->dias_pendiente = 0;
                return $inv;
            }

            $inv->pendiente_texto = "hace {$minutes}m";
            $inv->dias_pendiente = 0;
            return $inv;
        });

        return [$invoices, $estado, $q];
    }

    public function getDailySalesByPaymentMethod(Request $request): array
    {
        $fechaInput = trim((string) $request->query('fecha', ''));
        $bodegaId = (int) $request->query('bodega_id', 0);
        if ($bodegaId <= 0) {
            $bodegaId = null;
        }
        $fecha = null;

        if ($fechaInput !== '') {
            try {
                $fecha = Carbon::parse($fechaInput)->startOfDay();
            } catch (\Throwable $e) {
                $fecha = null;
            }
        }

        if (!$fecha) {
            $fecha = now()->startOfDay();
        }

        $fechaStr = $fecha->toDateString();

        $rows = $this->reporting->getDailySalesByPaymentMethod($fechaStr, $bodegaId);

        $totalCobrado = (float) $rows->sum('total_monto');
        $totalVentas = $this->reporting->getTotalVentasByDate($fechaStr, $bodegaId);
        $totalVentasGeneral = $this->reporting->getTotalVentasByDate($fechaStr, null);
        $totalsByBodega = $this->reporting->getTotalsByBodega($fechaStr);
        if ($bodegaId) {
            $totalsByBodega = $totalsByBodega->filter(function ($row) use ($bodegaId) {
                return (int) ($row->bodega_id ?? 0) === (int) $bodegaId;
            })->values();
        }
        $bodegas = $this->bodegas->getAll()->sortBy('nombre')->values();

        return [
            'rows' => $rows,
            'fecha' => $fechaStr,
            'totalCobrado' => $totalCobrado,
            'totalVentas' => $totalVentas,
            'totalVentasGeneral' => $totalVentasGeneral,
            'totalsByBodega' => $totalsByBodega,
            'bodegas' => $bodegas,
            'bodegaId' => $bodegaId,
        ];
    }

    public function exportDailySalesByPaymentMethod(Request $request): Response
    {
        $payload = $this->getDailySalesByPaymentMethod($request);

        $fecha = (string) ($payload['fecha'] ?? now()->toDateString());
        $filename = "venta_diaria_forma_pago_{$fecha}.xls";

        $html = $this->buildDailySalesByPaymentMethodExcelHtml($payload);

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildDailySalesByPaymentMethodExcelHtml(array $payload): string
    {
        $fecha = (string) ($payload['fecha'] ?? '');
        $bodegaId = $payload['bodegaId'] ?? null;
        $bodegas = $payload['bodegas'] ?? collect();

        $bodegaNombre = 'Todas las bodegas';
        if ($bodegaId) {
            $match = $bodegas->firstWhere('id', $bodegaId);
            if ($match) {
                $bodegaNombre = (string) $match->nombre;
            }
        }

        $totalVentasGeneral = (float) ($payload['totalVentasGeneral'] ?? 0);
        $totalVentas = (float) ($payload['totalVentas'] ?? 0);

        $totalsByBodega = $payload['totalsByBodega'] ?? collect();
        $rows = $payload['rows'] ?? collect();

        $money = fn($v) => number_format((float) $v, 2);

        $headerBg = '#1D4ED8';
        $headerText = '#FFFFFF';
        $subHeaderBg = '#DBEAFE';
        $border = '#BFDBFE';
        $text = '#0F172A';

        $html = [];
        $html[] = '<html><head><meta charset="utf-8" />'; 
        $html[] = '<style>';
        $html[] = "body{font-family:Calibri, Arial, sans-serif;color:{$text};}";
        $html[] = "table{border-collapse:collapse;width:100%;}";
        $html[] = "th,td{border:1px solid {$border};padding:6px;font-size:12px;}";
        $html[] = ".title{background:{$headerBg};color:{$headerText};font-weight:bold;font-size:16px;text-align:left;}";
        $html[] = ".section{background:{$subHeaderBg};font-weight:bold;}";
        $html[] = ".right{text-align:right;}";
        $html[] = ".muted{color:#334155;}";
        $html[] = '</style></head><body>';

        $html[] = '<table>';
        $html[] = '<tr><td class="title" colspan="4">Venta diaria por forma de pago</td></tr>';
        $html[] = '<tr><td class="section" colspan="4">Resumen</td></tr>';
        $html[] = '<tr><td class="muted">Fecha</td><td colspan="3">' . e($fecha) . '</td></tr>';
        $html[] = '<tr><td class="muted">Bodega</td><td colspan="3">' . e($bodegaNombre) . '</td></tr>';
        if (!$bodegaId) {
            $html[] = '<tr><td class="muted">Total facturado (todas bodegas)</td><td class="right" colspan="3">$' . $money($totalVentasGeneral) . '</td></tr>';
        }
        $html[] = '<tr><td class="muted">Total facturado (bodega seleccionada)</td><td class="right" colspan="3">$' . $money($totalVentas) . '</td></tr>';

        $html[] = '<tr><td colspan="4"></td></tr>';
        $html[] = '<tr><td class="section" colspan="4">Total facturado por bodega</td></tr>';
        $html[] = '<tr>';
        $html[] = '<th>Bodega</th><th class="right">Ventas</th><th class="right" colspan="2">Total facturado</th>';
        $html[] = '</tr>';

        if ($totalsByBodega->count()) {
            foreach ($totalsByBodega as $row) {
                $html[] = '<tr>';
                $html[] = '<td>' . e($row->bodega_nombre ?? 'N/D') . '</td>';
                $html[] = '<td class="right">' . (int) ($row->ventas ?? 0) . '</td>';
                $html[] = '<td class="right" colspan="2">$' . $money($row->total_facturado ?? 0) . '</td>';
                $html[] = '</tr>';
            }
        } else {
            $html[] = '<tr><td colspan="4">No hay ventas registradas para este dia.</td></tr>';
        }

        $html[] = '<tr><td colspan="4"></td></tr>';
        $html[] = '<tr><td class="section" colspan="4">Consolidado por forma de pago</td></tr>';
        $html[] = '<tr>';
        $html[] = '<th>Forma de pago</th><th class="right">Pagos</th><th class="right">Ventas</th><th class="right">Total</th>';
        $html[] = '</tr>';

        if ($rows->count()) {
            foreach ($rows as $row) {
                $html[] = '<tr>';
                $html[] = '<td>' . e($row->metodo ?? 'N/D') . '</td>';
                $html[] = '<td class="right">' . (int) ($row->pagos ?? 0) . '</td>';
                $html[] = '<td class="right">' . (int) ($row->ventas ?? 0) . '</td>';
                $html[] = '<td class="right">$' . $money($row->total_monto ?? 0) . '</td>';
                $html[] = '</tr>';
            }
        } else {
            $html[] = '<tr><td colspan="4">No hay ventas registradas para este dia.</td></tr>';
        }

        $html[] = '</table>';
        $html[] = '</body></html>';

        return implode('', $html);
    }

    public function getCashClosuresDaily(Request $request): array
    {
        $fechaInput = trim((string) $request->query('fecha', ''));
        $fecha = null;

        if ($fechaInput !== '') {
            try {
                $fecha = Carbon::parse($fechaInput)->startOfDay();
            } catch (\Throwable $e) {
                $fecha = null;
            }
        }

        if (!$fecha) {
            $fecha = now()->startOfDay();
        }

        $fechaStr = $fecha->toDateString();
        $sessions = $this->reporting->getClosedCashSessionsByDate($fechaStr);

        $sessions->transform(function ($session) {
            $openedAt = $session->opened_at;
            $closedAt = $session->closed_at;
            $session->duration_text = 'N/D';

            if ($openedAt && $closedAt) {
                $minutes = (int) $openedAt->diffInMinutes($closedAt);
                $hours = intdiv($minutes, 60);
                $mins = $minutes % 60;
                $session->duration_text = sprintf('%dh %02dm', $hours, $mins);
            }

            $session->payment_methods = collect();
            $session->payment_total = 0.0;
            if ($session->opened_at && $session->closed_at) {
                // Tolerancia para ventas registradas justo antes de apertura
                $from = $session->opened_at->copy()->subMinutes(5);
                $to = $session->closed_at;

                $session->payment_methods = $this->reporting->getPaymentMethodsBetween(
                    $from,
                    $to
                );
            }

            $result = strtoupper((string) ($session->result ?? ''));
            $session->result_label = $result === 'MATCH'
                ? 'CUADRA'
                : ($result === 'SHORT'
                    ? 'FALTANTE'
                    : ($result === 'OVER'
                        ? 'SOBRANTE'
                        : ($result !== '' ? $result : 'N/D')
                    )
                );

            return $session;
        });

        return [
            'fecha' => $fechaStr,
            'sessions' => $sessions,
        ];
    }

    public function exportCashClosuresDaily(Request $request): Response
    {
        $payload = $this->getCashClosuresDaily($request);

        $fecha = (string) ($payload['fecha'] ?? now()->toDateString());
        $filename = "cierres_caja_diarios_{$fecha}.xls";

        $html = $this->buildCashClosuresDailyExcelHtml($payload);

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildCashClosuresDailyExcelHtml(array $payload): string
    {
        $fecha = (string) ($payload['fecha'] ?? '');
        $sessions = $payload['sessions'] ?? collect();

        $headerBg = '#1D4ED8';
        $headerText = '#FFFFFF';
        $subHeaderBg = '#DBEAFE';
        $border = '#BFDBFE';
        $text = '#0F172A';

        $html = [];
        $html[] = '<html><head><meta charset="utf-8" />';
        $html[] = '<style>';
        $html[] = "body{font-family:Calibri, Arial, sans-serif;color:{$text};}";
        $html[] = "table{border-collapse:collapse;width:100%;}";
        $html[] = "th,td{border:1px solid {$border};padding:6px;font-size:12px;}";
        $html[] = ".title{background:{$headerBg};color:{$headerText};font-weight:bold;font-size:16px;text-align:left;}";
        $html[] = ".section{background:{$subHeaderBg};font-weight:bold;}";
        $html[] = ".right{text-align:right;}";
        $html[] = '</style></head><body>';

        $html[] = '<table>';
        $html[] = '<tr><td class="title" colspan="13">Cierres de caja diarios</td></tr>';
        $html[] = '<tr><td class="section" colspan="13">Resumen</td></tr>';
        $html[] = '<tr><td>Fecha</td><td colspan="12">' . e($fecha) . '</td></tr>';
        $html[] = '<tr><td>Total cierres</td><td colspan="12">' . (int) ($sessions->count()) . '</td></tr>';

        $html[] = '<tr><td colspan="13"></td></tr>';
        $html[] = '<tr>';
        $html[] = '<th>Caja</th><th>Apertura</th><th>Cierre</th><th>Horas</th><th>Usuario apertura</th><th>Usuario cierre</th>';
        $html[] = '<th class="right">Esperado</th><th class="right">Declarado</th><th class="right">Diferencia</th><th>Resultado</th>';
        $html[] = '<th>Formas de pago</th><th>Notas</th>';
        $html[] = '</tr>';

        foreach ($sessions as $s) {
            $metodos = ($s->payment_methods ?? collect())->filter()->values()->all();
            $metodosText = count($metodos) ? implode(', ', $metodos) : '-';
            $html[] = '<tr>';
            $html[] = '<td>#' . (int) $s->caja_id . '</td>';
            $html[] = '<td>' . e($s->opened_at?->format('Y-m-d H:i') ?? 'N/D') . '</td>';
            $html[] = '<td>' . e($s->closed_at?->format('Y-m-d H:i') ?? 'N/D') . '</td>';
            $html[] = '<td>' . e($s->duration_text ?? 'N/D') . '</td>';
            $html[] = '<td>' . e($s->opener?->name ?? 'N/D') . '</td>';
            $html[] = '<td>' . e($s->closer?->name ?? 'N/D') . '</td>';
            $html[] = '<td class="right">$' . number_format((float) ($s->expected_amount ?? 0), 2) . '</td>';
            $html[] = '<td class="right">$' . number_format((float) ($s->declared_amount ?? 0), 2) . '</td>';
            $html[] = '<td class="right">$' . number_format((float) ($s->difference_amount ?? 0), 2) . '</td>';
            $html[] = '<td>' . e($s->result_label ?? 'N/D') . '</td>';
            $html[] = '<td>' . e($metodosText) . '</td>';
            $html[] = '<td>' . e($s->notes ?? '-') . '</td>';
            $html[] = '</tr>';
        }

        if ($sessions->isEmpty()) {
            $html[] = '<tr><td colspan="13">No hay cierres registrados para este dia.</td></tr>';
        }

        $html[] = '</table>';
        $html[] = '</body></html>';

        return implode('', $html);
    }

    public function getMonthlySalesReport(Request $request): array
    {
        $selectedMes = trim((string) $request->query('mes', ''));

        $minMax = $this->reporting->getSalesMinMaxDates();
        $rows = collect();
        $months = collect();

        if ($minMax) {
            $fromAll = $minMax['min']->copy()->startOfMonth();
            $toAll = $minMax['max']->copy()->endOfMonth();

            $rowsAll = $this->reporting->getMonthlySalesSummary($fromAll, $toAll);

            $map = $rowsAll->mapWithKeys(function ($row) {
                $key = Carbon::parse($row->mes)->format('Y-m');
                return [$key => $row];
            });

            $cursor = $fromAll->copy();
            while ($cursor <= $toAll) {
                $key = $cursor->format('Y-m');
                $label = $cursor->locale('es')->translatedFormat('F Y');

                $months->push([
                    'value' => $key,
                    'label' => $label,
                ]);

                if ($map->has($key)) {
                    $rows->push($map->get($key));
                } else {
                    $rows->push((object) [
                        'mes' => $cursor->copy(),
                        'comprobantes' => 0,
                        'sub15' => 0,
                        'sub0' => 0,
                        'iva' => 0,
                        'total' => 0,
                    ]);
                }

                $cursor->addMonth();
            }

            if ($selectedMes !== '') {
                $rows = $rows->filter(function ($row) use ($selectedMes) {
                    $key = Carbon::parse($row->mes)->format('Y-m');
                    return $key === $selectedMes;
                })->values();
            }
        }

        $rowsChart = $rows;

        $perPage = 12;
        $page = (int) $request->query('monthly_page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $rowsTable = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'monthly_page',
                'query' => $request->query(),
            ]
        );

        return [
            'mes' => $selectedMes,
            'months' => $months,
            'rowsChart' => $rowsChart,
            'rowsTable' => $rowsTable,
        ];
    }

    public function exportMonthlySalesReport(Request $request): Response
    {
        $payload = $this->getMonthlySalesReport($request);

        $rows = $payload['rowsChart'] ?? collect();
        $mesFiltro = (string) ($payload['mes'] ?? '');

        $filename = $mesFiltro !== ''
            ? "ventas_mensuales_{$mesFiltro}.xls"
            : 'ventas_mensuales_todos.xls';

        $html = $this->buildMonthlySalesExcelHtml($rows);

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildMonthlySalesExcelHtml($rows): string
    {
        $headerBg = '#1D4ED8';
        $headerText = '#FFFFFF';
        $subHeaderBg = '#DBEAFE';
        $border = '#BFDBFE';
        $text = '#0F172A';

        $html = [];
        $html[] = '<html><head><meta charset="utf-8" />';
        $html[] = '<style>';
        $html[] = "body{font-family:Calibri, Arial, sans-serif;color:{$text};}";
        $html[] = "table{border-collapse:collapse;width:100%;}";
        $html[] = "th,td{border:1px solid {$border};padding:6px;font-size:12px;}";
        $html[] = ".title{background:{$headerBg};color:{$headerText};font-weight:bold;font-size:16px;text-align:left;}";
        $html[] = ".section{background:{$subHeaderBg};font-weight:bold;}";
        $html[] = ".right{text-align:right;}";
        $html[] = '</style></head><body>';

        $html[] = '<table>';
        $html[] = '<tr><td class="title" colspan="6">Reporte mensual de ventas</td></tr>';
        $html[] = '<tr><td class="section" colspan="6">Detalle mensual</td></tr>';
        $html[] = '<tr>';
        $html[] = '<th>Mes</th><th class="right">Comprobantes</th><th class="right">Sub 0</th><th class="right">Sub 15</th><th class="right">IVA 15</th><th class="right">Total</th>';
        $html[] = '</tr>';

        foreach ($rows as $row) {
            $mesLabel = $row->mes ? Carbon::parse($row->mes)->locale('es')->translatedFormat('F Y') : 'N/D';
            $html[] = '<tr>';
            $html[] = '<td>' . e($mesLabel) . '</td>';
            $html[] = '<td class="right">' . (int) ($row->comprobantes ?? 0) . '</td>';
            $html[] = '<td class="right">$' . number_format((float) ($row->sub0 ?? 0), 2) . '</td>';
            $html[] = '<td class="right">$' . number_format((float) ($row->sub15 ?? 0), 2) . '</td>';
            $html[] = '<td class="right">$' . number_format((float) ($row->iva ?? 0), 2) . '</td>';
            $html[] = '<td class="right">$' . number_format((float) ($row->total ?? 0), 2) . '</td>';
            $html[] = '</tr>';
        }

        if (empty($rows) || (is_countable($rows) && count($rows) === 0)) {
            $html[] = '<tr><td colspan="6">No hay ventas registradas.</td></tr>';
        }

        $html[] = '</table>';
        $html[] = '</body></html>';

        return implode('', $html);
    }

    public function getSalesRangeReport(Request $request): array
    {
        $fromInput = trim((string) $request->query('desde', ''));
        $toInput = trim((string) $request->query('hasta', ''));
        $from = null;
        $to = null;

        if ($fromInput !== '') {
            try {
                $from = Carbon::parse($fromInput)->startOfDay();
            } catch (\Throwable $e) {
                $from = null;
            }
        }

        if ($toInput !== '') {
            try {
                $to = Carbon::parse($toInput)->endOfDay();
            } catch (\Throwable $e) {
                $to = null;
            }
        }

        if (!$from && !$to) {
            $to = now()->endOfDay();
            $from = $to->copy()->subDays(30)->startOfDay();
        } elseif ($from && !$to) {
            $to = $from->copy()->endOfDay();
        } elseif (!$from && $to) {
            $from = $to->copy()->startOfDay();
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $rangeSummary = $this->reporting->getSalesSummaryByRange($from, $to);
        $rangeFrequency = $this->reporting->getSalesFrequencyByRange($from, $to);
        $rangeTable = $this->reporting->getSalesFrequencyByRangePaginated($from, $to, 30);

        return [
            'desde' => $from->toDateString(),
            'hasta' => $to->toDateString(),
            'rangeSummary' => $rangeSummary,
            'rangeFrequency' => $rangeFrequency,
            'rangeTable' => $rangeTable,
        ];
    }

    public function exportSalesRangeReport(Request $request): Response
    {
        $payload = $this->getSalesRangeReport($request);

        $desde = (string) ($payload['desde'] ?? '');
        $hasta = (string) ($payload['hasta'] ?? '');
        $filename = "ventas_rango_{$desde}_{$hasta}.xls";

        $html = $this->buildSalesRangeExcelHtml($payload);

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildSalesRangeExcelHtml(array $payload): string
    {
        $rangeSummary = $payload['rangeSummary'] ?? null;
        $rangeFrequency = $payload['rangeFrequency'] ?? collect();
        $desde = (string) ($payload['desde'] ?? '');
        $hasta = (string) ($payload['hasta'] ?? '');

        $headerBg = '#1D4ED8';
        $headerText = '#FFFFFF';
        $subHeaderBg = '#DBEAFE';
        $border = '#BFDBFE';
        $text = '#0F172A';

        $html = [];
        $html[] = '<html><head><meta charset="utf-8" />';
        $html[] = '<style>';
        $html[] = "body{font-family:Calibri, Arial, sans-serif;color:{$text};}";
        $html[] = "table{border-collapse:collapse;width:100%;}";
        $html[] = "th,td{border:1px solid {$border};padding:6px;font-size:12px;}";
        $html[] = ".title{background:{$headerBg};color:{$headerText};font-weight:bold;font-size:16px;text-align:left;}";
        $html[] = ".section{background:{$subHeaderBg};font-weight:bold;}";
        $html[] = ".right{text-align:right;}";
        $html[] = '</style></head><body>';

        $html[] = '<table>';
        $html[] = '<tr><td class="title" colspan="6">Ventas por rango de fechas</td></tr>';
        $html[] = '<tr><td class="section" colspan="6">Resumen</td></tr>';
        $html[] = '<tr><td>Desde</td><td colspan="5">' . e($desde) . '</td></tr>';
        $html[] = '<tr><td>Hasta</td><td colspan="5">' . e($hasta) . '</td></tr>';
        $html[] = '<tr><td class="section" colspan="6">Totales</td></tr>';
        $html[] = '<tr>';
        $html[] = '<th>Rango</th><th class="right">Comprobantes</th><th class="right">Sub 0</th><th class="right">Sub 15</th><th class="right">IVA 15</th><th class="right">Total</th>';
        $html[] = '</tr>';
        $html[] = '<tr>';
        $html[] = '<td>' . e($desde . ' a ' . $hasta) . '</td>';
        $html[] = '<td class="right">' . (int) ($rangeSummary->comprobantes ?? 0) . '</td>';
        $html[] = '<td class="right">$' . number_format((float) ($rangeSummary->sub0 ?? 0), 2) . '</td>';
        $html[] = '<td class="right">$' . number_format((float) ($rangeSummary->sub15 ?? 0), 2) . '</td>';
        $html[] = '<td class="right">$' . number_format((float) ($rangeSummary->iva ?? 0), 2) . '</td>';
        $html[] = '<td class="right">$' . number_format((float) ($rangeSummary->total ?? 0), 2) . '</td>';
        $html[] = '</tr>';

        $html[] = '<tr><td colspan="6"></td></tr>';
        $html[] = '<tr><td class="section" colspan="6">Detalle diario</td></tr>';
        $html[] = '<tr>';
        $html[] = '<th>Dia</th><th class="right">Comprobantes</th><th class="right" colspan="4">Total</th>';
        $html[] = '</tr>';

        foreach ($rangeFrequency as $row) {
            $html[] = '<tr>';
            $html[] = '<td>' . e($row->dia ?? '') . '</td>';
            $html[] = '<td class="right">' . (int) ($row->comprobantes ?? 0) . '</td>';
            $html[] = '<td class="right" colspan="4">$' . number_format((float) ($row->total ?? 0), 2) . '</td>';
            $html[] = '</tr>';
        }

        if ($rangeFrequency->isEmpty()) {
            $html[] = '<tr><td colspan="6">No hay ventas registradas en este rango.</td></tr>';
        }

        $html[] = '</table>';
        $html[] = '</body></html>';

        return implode('', $html);
    }

    public function getTopProductsReport(Request $request): array
    {
        $fromInput = trim((string) $request->query('desde', ''));
        $toInput = trim((string) $request->query('hasta', ''));
        $from = null;
        $to = null;

        if ($fromInput !== '') {
            try {
                $from = Carbon::parse($fromInput)->startOfDay();
            } catch (\Throwable $e) {
                $from = null;
            }
        }

        if ($toInput !== '') {
            try {
                $to = Carbon::parse($toInput)->endOfDay();
            } catch (\Throwable $e) {
                $to = null;
            }
        }

        if (!$from && !$to) {
            $to = now()->endOfDay();
            $from = $to->copy()->subDays(30)->startOfDay();
        } elseif ($from && !$to) {
            $to = $from->copy()->endOfDay();
        } elseif (!$from && $to) {
            $from = $to->copy()->startOfDay();
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $rows = $this->reporting->getTopProductsByQty($from, $to, 5);

        return [
            'desde' => $from->toDateString(),
            'hasta' => $to->toDateString(),
            'rows' => $rows,
        ];
    }

    public function exportTopProductsReport(Request $request): Response
    {
        $payload = $this->getTopProductsReport($request);

        $desde = (string) ($payload['desde'] ?? '');
        $hasta = (string) ($payload['hasta'] ?? '');
        $filename = "top_productos_{$desde}_{$hasta}.xls";

        $html = $this->buildTopProductsExcelHtml($payload);

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildTopProductsExcelHtml(array $payload): string
    {
        $rows = $payload['rows'] ?? collect();
        $desde = (string) ($payload['desde'] ?? '');
        $hasta = (string) ($payload['hasta'] ?? '');

        $headerBg = '#1D4ED8';
        $headerText = '#FFFFFF';
        $subHeaderBg = '#DBEAFE';
        $border = '#BFDBFE';
        $text = '#0F172A';

        $html = [];
        $html[] = '<html><head><meta charset="utf-8" />';
        $html[] = '<style>';
        $html[] = "body{font-family:Calibri, Arial, sans-serif;color:{$text};}";
        $html[] = "table{border-collapse:collapse;width:100%;}";
        $html[] = "th,td{border:1px solid {$border};padding:6px;font-size:12px;}";
        $html[] = ".title{background:{$headerBg};color:{$headerText};font-weight:bold;font-size:16px;text-align:left;}";
        $html[] = ".section{background:{$subHeaderBg};font-weight:bold;}";
        $html[] = ".right{text-align:right;}";
        $html[] = '</style></head><body>';

        $html[] = '<table>';
        $html[] = '<tr><td class="title" colspan="4">Top productos más vendidos</td></tr>';
        $html[] = '<tr><td class="section" colspan="4">Rango</td></tr>';
        $html[] = '<tr><td>Desde</td><td colspan="3">' . e($desde) . '</td></tr>';
        $html[] = '<tr><td>Hasta</td><td colspan="3">' . e($hasta) . '</td></tr>';
        $html[] = '<tr><td class="section" colspan="4">Top 5</td></tr>';
        $html[] = '<tr><th>Producto</th><th class="right">Cantidad</th><th class="right">Total</th></tr>';

        foreach ($rows as $row) {
            $html[] = '<tr>';
            $html[] = '<td>' . e($row->producto_nombre ?? 'N/D') . '</td>';
            $html[] = '<td class="right">' . (int) ($row->cantidad ?? 0) . '</td>';
            $html[] = '<td class="right">$' . number_format((float) ($row->total ?? 0), 2) . '</td>';
            $html[] = '</tr>';
        }

        if (empty($rows) || (is_countable($rows) && count($rows) === 0)) {
            $html[] = '<tr><td colspan="3">No hay ventas registradas en este rango.</td></tr>';
        }

        $html[] = '</table>';
        $html[] = '</body></html>';

        return implode('', $html);
    }

    public function getInventoryByProductReport(Request $request): array
    {
        $bodegaId = (int) $request->query('bodega_id', 0);
        $categoria = trim((string) $request->query('categoria', ''));
        $q = trim((string) $request->query('q', ''));

        $filters = [
            'bodega_id' => $bodegaId,
            'categoria' => $categoria,
            'q' => $q,
        ];

        $rows = $this->reporting->getInventoryReport($filters, 12);
        $chartRows = $this->reporting->getInventoryChartData($filters, 10);

        $rows->getCollection()->transform(function ($row) {
            $min = (int) ($row->stock_minimo ?? 0);
            $stock = (int) ($row->stock_actual ?? 0);
            $row->is_low = $stock < $min;
            $row->is_negative = $stock < 0;
            return $row;
        });

        $bodegas = $this->bodegas->getAll()->sortBy('nombre')->values();
        $categorias = $this->reporting->getProductCategories();

        return [
            'rows' => $rows,
            'chartRows' => $chartRows,
            'bodegas' => $bodegas,
            'categorias' => $categorias,
            'bodegaId' => $bodegaId,
            'categoria' => $categoria,
            'q' => $q,
        ];
    }

    public function exportInventoryByProductReport(Request $request): Response
    {
        $payload = $this->getInventoryByProductReport($request);

        $bodegaId = (int) ($payload['bodegaId'] ?? 0);
        $categoria = (string) ($payload['categoria'] ?? '');
        $q = (string) ($payload['q'] ?? '');

        $rows = $payload['rows']?->getCollection() ?? collect();

        $filename = 'inventario_productos.xls';
        if ($bodegaId) {
            $filename = "inventario_bodega_{$bodegaId}.xls";
        }

        $html = $this->buildInventoryExcelHtml($rows, $bodegaId, $categoria, $q);

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildInventoryExcelHtml($rows, int $bodegaId, string $categoria, string $q): string
    {
        $headerBg = '#1D4ED8';
        $headerText = '#FFFFFF';
        $subHeaderBg = '#DBEAFE';
        $border = '#BFDBFE';
        $text = '#0F172A';

        $html = [];
        $html[] = '<html><head><meta charset="utf-8" />';
        $html[] = '<style>';
        $html[] = "body{font-family:Calibri, Arial, sans-serif;color:{$text};}";
        $html[] = "table{border-collapse:collapse;width:100%;}";
        $html[] = "th,td{border:1px solid {$border};padding:6px;font-size:12px;}";
        $html[] = ".title{background:{$headerBg};color:{$headerText};font-weight:bold;font-size:16px;text-align:left;}";
        $html[] = ".section{background:{$subHeaderBg};font-weight:bold;}";
        $html[] = ".right{text-align:right;}";
        $html[] = '</style></head><body>';

        $html[] = '<table>';
        $html[] = '<tr><td class="title" colspan="6">Inventario por producto y bodega</td></tr>';
        $html[] = '<tr><td class="section" colspan="6">Filtros</td></tr>';
        $html[] = '<tr><td>Bodega ID</td><td colspan="5">' . ($bodegaId ?: 'Todas') . '</td></tr>';
        $html[] = '<tr><td>Categoria</td><td colspan="5">' . e($categoria ?: 'Todas') . '</td></tr>';
        $html[] = '<tr><td>Busqueda</td><td colspan="5">' . e($q ?: '-') . '</td></tr>';

        $html[] = '<tr><td class="section" colspan="6">Detalle</td></tr>';
        $html[] = '<tr>';
        $html[] = '<th>Producto</th><th>Categoria</th><th>Bodega</th><th class="right">Stock actual</th><th class="right">Stock minimo</th><th>Estado</th>';
        $html[] = '</tr>';

        foreach ($rows as $row) {
            $min = (int) ($row->stock_minimo ?? 0);
            $stock = (int) ($row->stock_actual ?? 0);
            $estado = $stock < 0 ? 'NEGATIVO' : ($stock < $min ? 'BAJO' : 'OK');
            $html[] = '<tr>';
            $html[] = '<td>' . e($row->producto_nombre ?? 'N/D') . '</td>';
            $html[] = '<td>' . e($row->categoria ?? 'N/D') . '</td>';
            $html[] = '<td>' . e($row->bodega_nombre ?? 'N/D') . '</td>';
            $html[] = '<td class="right">' . $stock . '</td>';
            $html[] = '<td class="right">' . $min . '</td>';
            $html[] = '<td>' . $estado . '</td>';
            $html[] = '</tr>';
        }

        if (empty($rows) || (is_countable($rows) && count($rows) === 0)) {
            $html[] = '<tr><td colspan="6">No hay productos para los filtros seleccionados.</td></tr>';
        }

        $html[] = '</table>';
        $html[] = '</body></html>';

        return implode('', $html);
    }
}
