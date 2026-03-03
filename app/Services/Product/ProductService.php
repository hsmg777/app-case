<?php

namespace App\Services\Product;

use App\Jobs\ExportProductsExcelJob;
use App\Jobs\ProcessProductsImportJob;
use App\Models\Store\Bodega;
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

    public function getByBodegaWithStock(
        int $bodegaId,
        ?string $search = null,
        int $limit = 200
    )
    {
        $products = $this->repo->allForPos(
            onlyActive: true,
            search: $search,
            limit: $limit
        );
        $products->load([
            'inventory' => function ($query) use ($bodegaId) {
                $query->select(['id', 'producto_id', 'bodega_id', 'percha_id', 'stock_actual'])
                    ->where('bodega_id', $bodegaId)
                    ->with(['percha:id,nombre,codigo']);
            },
        ]);

        $bodegaNombre = Bodega::query()->where('id', $bodegaId)->value('nombre') ?? 'Bodega Actual';

        return $products->map(function ($product) use ($bodegaId, $bodegaNombre) {
            $inventoryRows = $product->inventory ?? collect();
            $totalStock = (int) $inventoryRows->sum('stock_actual');

            $perchas = $inventoryRows->map(function ($inv) {
                return [
                    'id' => $inv->percha_id,
                    'nombre' => $inv->percha?->nombre ?? $inv->percha?->codigo ?? 'Sin percha',
                    'stock' => (int) $inv->stock_actual,
                ];
            })->values()->all();

            $price = $product->price;
            $pricePayload = $price ? [
                'id' => $price->id,
                'producto_id' => $price->producto_id,
                'precio_unitario' => $price->precio_unitario,
                'precio_por_cantidad' => $price->precio_por_cantidad,
                'cantidad_min' => $price->cantidad_min,
                'cantidad_max' => $price->cantidad_max,
                'precio_por_caja' => $price->precio_por_caja,
                'unidades_por_caja' => $price->unidades_por_caja,
                'moneda' => $price->moneda,
            ] : null;

            return [
                'id' => $product->id,
                'nombre' => $product->nombre,
                'codigo_barras' => $product->codigo_barras,
                'codigo_interno' => $product->codigo_interno,
                'categoria' => $product->categoria,
                'unidad_medida' => $product->unidad_medida,
                'iva_porcentaje' => $product->iva_porcentaje,
                'stock_actual' => $totalStock,
                'bodega_id' => $bodegaId,
                'bodega_nombre' => $bodegaNombre,
                'perchas' => $perchas,
                'price' => $pricePayload,
                'product_prices' => $pricePayload ? [$pricePayload] : [],
            ];
        })->values()->all();
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
