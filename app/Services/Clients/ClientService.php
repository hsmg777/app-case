<?php

namespace App\Services\Clients;

use App\Models\Clients\Client;
use App\Repositories\Clients\ClientRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ClientService
{
    protected ClientRepository $repository;

    public function __construct(ClientRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Listar clientes con filtros y paginación.
     *
     * Filtros soportados (propuestos):
     * - search: busca en business, identificación, teléfono
     * - tipo: natural / juridico
     * - estado: activo / inactivo
     * - ciudad
     */
    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->repository->list($filters, $perPage);
    }

    /**
     * Listar TODOS los clientes con filtros (sin paginación).
     */
    public function listAll(array $filters = [])
    {
        return $this->repository->listAll($filters);
    }

    /**
     * Exportar clientes a Excel (HTML).
     */
    public function exportClients(Request $request): Response
    {
        $filters = $request->only(['search', 'tipo', 'estado', 'ciudad']);
        $rows = $this->listAll($filters);

        $filename = 'clientes_' . now()->toDateString() . '.xls';
        $html = $this->buildClientsExcelHtml($rows, $filters);

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Obtener un cliente por ID.
     */
    public function find(int $id): Client
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Crear un cliente nuevo con sus emails (si vienen).
     *
     * $data puede incluir:
     * - tipo_identificacion, identificacion, business, tipo,
     *   telefono, direccion, ciudad, estado
     * - emails: array de strings (opcional)
     */
    public function create(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            $emails = $data['emails'] ?? [];
            unset($data['emails']);

            // Crear cliente
            $client = $this->repository->create($data);

            // Crear correos si vienen
            if (!empty($emails) && is_array($emails)) {
                $this->repository->syncEmails($client, $emails);
            }

            return $client->load('emails');
        });
    }

    /**
     * Actualizar un cliente y, opcionalmente, reemplazar sus emails.
     *
     * Si en $data viene 'emails', se sobreescriben los correos anteriores
     * por la nueva lista. Si no viene 'emails', se mantiene lo que ya tiene.
     */
    public function update(int $id, array $data): Client
    {
        return DB::transaction(function () use ($id, $data) {
            $emails = $data['emails'] ?? null;
            unset($data['emails']);

            $client = $this->repository->update($id, $data);

            if (is_array($emails)) {
                $this->repository->syncEmails($client, $emails);
            }

            return $client->load('emails');
        });
    }

    /**
     * Eliminar (o soft delete si luego lo manejas así) un cliente.
     */
    public function delete(int $id): void
    {
        $this->repository->delete($id);
    }

    /**
     * Buscar cliente por tipo + identificación (útil para facturación).
     */
    public function findByIdentificacion(string $tipoIdentificacion, string $identificacion): ?Client
    {
        return $this->repository->findByIdentificacion($tipoIdentificacion, $identificacion);
    }

    /**
     * Flujo pensado para la futura facturación:
     * - Si existe cliente con ese tipo + identificación → lo devuelve.
     * - Si no existe → lo crea con los datos enviados.
     *
     * Esto NO lo vas a usar todavía en el módulo puro de clientes,
     * pero queda listo para el módulo de facturación.
     */
    public function findOrCreateForInvoice(array $data): Client
    {
        $existing = $this->findByIdentificacion(
            $data['tipo_identificacion'],
            $data['identificacion'],
        );

        if ($existing) {
            return $existing;
        }

        return $this->create($data);
    }

    private function buildClientsExcelHtml($rows, array $filters = []): string
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
        $html[] = ".muted{color:#334155;}";
        $html[] = '</style></head><body>';

        $html[] = '<table>';
        $html[] = '<tr><td class="title" colspan="7">Listado de clientes</td></tr>';
        $html[] = '<tr><td class="muted">Total clientes</td><td colspan="6">' . (int) $rows->count() . '</td></tr>';
        $html[] = '<tr><td colspan="7"></td></tr>';
        $html[] = '<tr>';
        $html[] = '<th>Identificación</th>';
        $html[] = '<th>Business</th>';
        $html[] = '<th>Tipo</th>';
        $html[] = '<th>Teléfono</th>';
        $html[] = '<th>Ciudad</th>';
        $html[] = '<th>Estado</th>';
        $html[] = '<th>Emails</th>';
        $html[] = '</tr>';

        if ($rows->count()) {
            foreach ($rows as $client) {
                $emails = $client->emails ?? collect();
                $emailsText = $emails->pluck('email')->filter()->implode(', ');

                $html[] = '<tr>';
                $html[] = '<td>' . e(($client->tipo_identificacion ?? '') . ' ' . ($client->identificacion ?? '')) . '</td>';
                $html[] = '<td>' . e($client->business ?? '') . '</td>';
                $html[] = '<td>' . e(ucfirst((string) ($client->tipo ?? ''))) . '</td>';
                $html[] = '<td>' . e($client->telefono ?? '-') . '</td>';
                $html[] = '<td>' . e($client->ciudad ?? '-') . '</td>';
                $html[] = '<td>' . e($client->estado ?? '-') . '</td>';
                $html[] = '<td>' . e($emailsText ?: '-') . '</td>';
                $html[] = '</tr>';
            }
        } else {
            $html[] = '<tr><td colspan="7">No se encontraron clientes con los filtros actuales.</td></tr>';
        }

        $html[] = '</table>';
        $html[] = '</body></html>';

        return implode('', $html);
    }
}
