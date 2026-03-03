<?php

namespace App\Services\Product;

use App\Jobs\ExportProductsExcelJob;
use App\Jobs\ProcessProductsImportJob;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductImportRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class ProductService
{
    protected ProductRepository $repo;
    protected ProductImportRepository $importRepo;

    public function __construct(
        ProductRepository $repo,
        ProductImportRepository $importRepo
    )
    {
        $this->repo = $repo;
        $this->importRepo = $importRepo;
    }

    public function getAll(?bool $onlyActive = true)
    {
        return $this->repo->all(
            onlyActive: $onlyActive,
            withPrice: true,
            withTierPrices: true
        );
    }

    public function getTablePage(
        ?bool $onlyActive = true,
        ?string $search = null,
        ?string $categoria = null,
        int $page = 1,
        int $perPage = 10
    ): array {
        $paginator = $this->repo->paginateForTable(
            onlyActive: $onlyActive,
            search: $search,
            categoria: $categoria,
            page: $page,
            perPage: $perPage
        );

        return [
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
            'categories' => $this->repo->getDistinctCategories($onlyActive),
        ];
    }

    public function getById($id)
    {
        return $this->repo->find(
            $id,
            withPrice: true,
            withTierPrices: true
        );
    }

    public function create(array $data)
    {
        if (
            !array_key_exists('iva_porcentaje', $data) ||
            $data['iva_porcentaje'] === null ||
            $data['iva_porcentaje'] === ''
        ) {
            $data['iva_porcentaje'] = 15.00;
        }

        return $this->repo->create($data);
    }

    public function update($id, array $data)
    {
        $product = $this->repo->find($id);

        if (
            array_key_exists('iva_porcentaje', $data) &&
            ($data['iva_porcentaje'] === null || $data['iva_porcentaje'] === '')
        ) {
            $data['iva_porcentaje'] = 15.00;
        }

        return $this->repo->update($product, $data);
    }

    public function delete($id)
    {
        $product = $this->repo->find($id);
        return $this->repo->delete($product);
    }

    public function getByBodegaWithStock(int $bodegaId)
    {
        // 1. Traer TODOS los productos activos (o todos si se requiere) + Relación Inventario (todas las bodegas)
        // Eliminamos el filtro 'stock_actual > 0' para traer todo.
        // Y traemos inventario de TODAS las bodegas para hacer el global search.

        $products = $this->repo->all(onlyActive: true, withPrice: true, withTierPrices: true);

        // Eager load manual para optimizar o usar el query builder.
        // Pero como $repo->all retorna Collection de modelos, cargamos relaciones:
        $products->load(['inventory.percha', 'inventory.bodega']);

        $results = [];

        foreach ($products as $product) {
            $prodData = $product->toArray();

            // Agrupar inventarios de ESTE producto por bodega_id
            $invByBodega = $product->inventory->groupBy('bodega_id');

            // Lista de bodegas que tienen registro de inventario
            $existingBodegas = $invByBodega->keys()->all();

            // Asegurar que la bodega actual ($bodegaId) esté en la lista a procesar,
            // aunque no tenga inventario (para mostrarla con stock 0).
            $bodegasToProcess = array_unique(array_merge($existingBodegas, [$bodegaId]));

            foreach ($bodegasToProcess as $bId) {
                // Filtrar inventarios para esta bodega especifica
                $invList = $invByBodega->get($bId) ?? collect();

                $totalStock = $invList->sum('stock_actual');

                // Construir lista de perchas
                $perchas = $invList->map(function ($inv) {
                    return [
                        'id' => $inv->percha_id,
                        'nombre' => $inv->percha ? $inv->percha->nombre : 'Sin percha',
                        'stock' => $inv->stock_actual,
                    ];
                })->values()->toArray();

                // Clonamos datos del producto base
                $row = $prodData;

                // Sobrescribimos/Agregamos datos de contexto bodega
                $row['stock_actual'] = $totalStock;
                $row['bodega_id'] = $bId;
                $row['perchas'] = $perchas; // Lista de perchas en ESTA bodega

                // Info extra de la bodega (nombre) si existe
                $firstInv = $invList->first();
                // Determinar nombre bodega
                if ($firstInv && $firstInv->bodega) {
                    $row['bodega_nombre'] = $firstInv->bodega->nombre;
                } else {
                    // Intento fallback si es la local y no hay inventario
                    $row['bodega_nombre'] = ($bId == $bodegaId) ? 'Bodega Actual' : "Bodega #$bId";
                }

                $results[] = $row;
            }
        }

        return $results;
    }

    public function setEstado(string $id, bool $estado): void
    {
        $product = $this->repo->find($id);
        $this->repo->setEstado($product, $estado);
    }

    public function exportProductsExcel(array $filters = []): BinaryFileResponse
    {
        $estado = $filters['estado'] ?? 'activos';
        $onlyActive = match ($estado) {
            'inactivos' => false,
            'todos' => null,
            default => true,
        };

        $search = trim((string) ($filters['q'] ?? ''));
        $categoria = trim((string) ($filters['categoria'] ?? ''));

        $rows = $this->repo->getForExport(
            onlyActive: $onlyActive,
            search: $search !== '' ? $search : null,
            categoria: $categoria !== '' ? $categoria : null
        );

        $timestamp = now()->format('Ymd_His');
        $filename = "productos_{$timestamp}.xls";
        $storagePath = "private/exports/productos/{$filename}";

        Bus::dispatchSync(new ExportProductsExcelJob(
            products: $rows->toArray(),
            storagePath: $storagePath,
            filters: [
                'estado' => $estado,
                'q' => $search,
                'categoria' => $categoria,
            ]
        ));

        return response()->download(
            Storage::disk('local')->path($storagePath),
            $filename,
            [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            ]
        )->deleteFileAfterSend(true);
    }

    public function downloadImportTemplate(): BinaryFileResponse
    {
        $filename = 'plantilla_productos_importacion.xlsx';
        $storagePath = "private/templates/{$filename}";
        $absolutePath = Storage::disk('local')->path($storagePath);

        $script = base_path('scripts/xlsx/products-template.cjs');
        $process = new Process(['node', $script, $absolutePath]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                $process->getErrorOutput() ?: 'No se pudo generar la plantilla de importacion.'
            );
        }

        return response()->download(
            $absolutePath,
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    public function startProductsImport(UploadedFile $file, ?int $userId = null): array
    {
        $storedPath = $file->store('private/imports/products', 'local');

        $import = $this->importRepo->create([
            'user_id' => $userId,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'status' => 'pending',
        ]);

        ProcessProductsImportJob::dispatch($import->id);

        return [
            'import_id' => $import->id,
            'status' => $import->status,
        ];
    }

    public function getImportStatus(int $importId): array
    {
        $import = $this->importRepo->findOrFail($importId);

        return [
            'id' => $import->id,
            'status' => $import->status,
            'total_rows' => (int) $import->total_rows,
            'processed_rows' => (int) $import->processed_rows,
            'created_count' => (int) $import->created_count,
            'failed_count' => (int) $import->failed_count,
            'error_log' => $import->error_log,
            'started_at' => $import->started_at?->toDateTimeString(),
            'finished_at' => $import->finished_at?->toDateTimeString(),
        ];
    }
}
