<?php

namespace App\Repositories\Clients;

use App\Models\Clients\Client;
use App\Models\Clients\ClientEmail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientRepository
{
    /**
     * Listar clientes con filtros y paginación.
     *
     * Filtros esperados en $filters:
     * - search: busca en business, identificación y teléfono
     * - tipo: natural | juridico
     * - estado: activo | inactivo
     * - ciudad: nombre de la ciudad
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Client::with('emails');

        // Filtro de búsqueda general
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('business', 'like', "%{$search}%")
                  ->orWhere('identificacion', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        // Filtro por tipo (natural / juridico)
        if (!empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        // Filtro por estado (activo / inactivo)
        if (!empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        // Filtro por ciudad
        if (!empty($filters['ciudad'])) {
            $query->where('ciudad', 'like', "%{$filters['ciudad']}%");
        }

        // Orden por defecto (últimos creados primero)
        $query->orderByDesc('created_at');

        return $query->paginate($perPage);
    }

    public function listAll(array $filters = [])
    {
        $query = Client::with('emails');

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('business', 'like', "%{$search}%")
                    ->orWhere('identificacion', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        if (!empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (!empty($filters['ciudad'])) {
            $query->where('ciudad', 'like', "%{$filters['ciudad']}%");
        }

        $query->orderByDesc('created_at');

        return $query->get();
    }

    /**
     * Obtener un cliente por ID (lanza 404 si no existe).
     */
    public function findOrFail(int $id): Client
    {
        return Client::with('emails')->findOrFail($id);
    }

    /**
     * Crear un cliente.
     */
    public function create(array $data): Client
    {
        return Client::create($data);
    }

    /**
     * Actualizar un cliente.
     */
    public function update(int $id, array $data): Client
    {
        $client = Client::findOrFail($id);
        $client->update($data);

        return $client;
    }

    /**
     * Eliminar un cliente.
     * Si en el modelo usas SoftDeletes, hará soft delete.
     */
    public function delete(int $id): void
    {
        $client = Client::findOrFail($id);
        $client->delete();
    }

    /**
     * Buscar cliente por tipo + identificación.
     * Útil para el módulo de facturación.
     */
    public function findByIdentificacion(string $tipoIdentificacion, string $identificacion): ?Client
    {
        return Client::with('emails')
            ->where('tipo_identificacion', $tipoIdentificacion)
            ->where('identificacion', $identificacion)
            ->first();
    }

    /**
     * Sincronizar correos electrónicos del cliente.
     *
     * Estrategia simple:
     * - Borra los emails actuales
     * - Crea los nuevos a partir del array
     *
     * $emails debe ser un array de strings:
     * ['correo1@mail.com', 'correo2@mail.com', ...]
     */
    public function syncEmails(Client $client, array $emails): void
    {
        // Eliminar correos actuales
        $client->emails()->delete();

        // Crear los nuevos
        $cleanEmails = array_filter(array_map('trim', $emails));

        foreach ($cleanEmails as $email) {
            if ($email === '') {
                continue;
            }

            $client->emails()->create([
                'email' => $email,
            ]);
        }
    }
}
