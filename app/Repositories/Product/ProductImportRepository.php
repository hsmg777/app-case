<?php

namespace App\Repositories\Product;

use App\Models\Product\ProductImport;

class ProductImportRepository
{
    public function create(array $data): ProductImport
    {
        return ProductImport::query()->create($data);
    }

    public function findOrFail(int $id): ProductImport
    {
        return ProductImport::query()->findOrFail($id);
    }

    public function markProcessing(ProductImport $import): ProductImport
    {
        $import->update([
            'status' => 'processing',
            'started_at' => now(),
            'error_log' => null,
        ]);

        return $import->refresh();
    }

    public function markCompleted(
        ProductImport $import,
        int $totalRows,
        int $processedRows,
        int $createdCount,
        int $failedCount,
        ?string $errorLog = null
    ): ProductImport {
        $import->update([
            'status' => 'completed',
            'total_rows' => $totalRows,
            'processed_rows' => $processedRows,
            'created_count' => $createdCount,
            'failed_count' => $failedCount,
            'error_log' => $errorLog,
            'finished_at' => now(),
        ]);

        return $import->refresh();
    }

    public function markFailed(ProductImport $import, string $error): ProductImport
    {
        $import->update([
            'status' => 'failed',
            'error_log' => $error,
            'finished_at' => now(),
        ]);

        return $import->refresh();
    }
}
