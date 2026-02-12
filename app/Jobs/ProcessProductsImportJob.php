<?php

namespace App\Jobs;

use App\Repositories\Product\ProductImportRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Product\ProductPriceService;
use App\Services\Product\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ProcessProductsImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $importId)
    {
    }

    public function handle(
        ProductImportRepository $imports,
        ProductRepository $products,
        ProductService $service,
        ProductPriceService $priceService
    ): void {
        $import = $imports->findOrFail($this->importId);
        $imports->markProcessing($import);

        try {
            $filePath = Storage::disk('local')->path($import->file_path);
            $script = base_path('scripts/xlsx/products-to-json.cjs');

            $process = new Process(['node', $script, $filePath]);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput() ?: 'No se pudo leer el archivo XLSX.');
            }

            $rows = json_decode($process->getOutput(), true);
            if (!is_array($rows)) {
                throw new \RuntimeException('Formato de datos invalido en el archivo XLSX.');
            }

            $errors = [];
            $createdCount = 0;
            $failedCount = 0;
            $processedRows = 0;
            $seenInternos = [];
            $seenBarras = [];
            $rowNumber = 1;

            foreach ($rows as $row) {
                $rowNumber++;

                if (!is_array($row) || $this->isEmptyRow($row)) {
                    continue;
                }

                $processedRows++;

                $nombre = trim((string) ($row['nombre'] ?? ''));
                $codigoInterno = trim((string) ($row['codigo_interno'] ?? ''));
                $codigoBarras = trim((string) ($row['codigo_barras'] ?? ''));
                $categoria = trim((string) ($row['categoria'] ?? ''));
                $unidadMedida = trim((string) ($row['unidad_medida'] ?? ''));
                $stockMinimoRaw = trim((string) ($row['stock_minimo'] ?? ''));
                $descripcion = trim((string) ($row['descripcion'] ?? ''));
                $ivaRaw = trim((string) ($row['iva_porcentaje'] ?? ''));
                $precioUnitarioRaw = trim((string) ($row['precio_unitario'] ?? ''));

                $rowErrors = [];

                if ($nombre === '') {
                    $rowErrors[] = 'nombre es obligatorio';
                } elseif (mb_strlen($nombre) > 255) {
                    $rowErrors[] = 'nombre supera 255 caracteres';
                }

                if ($unidadMedida === '') {
                    $rowErrors[] = 'unidad_medida es obligatorio';
                } elseif (mb_strlen($unidadMedida) > 50) {
                    $rowErrors[] = 'unidad_medida supera 50 caracteres';
                }

                if ($stockMinimoRaw === '' || !is_numeric($stockMinimoRaw) || (int) $stockMinimoRaw < 0) {
                    $rowErrors[] = 'stock_minimo debe ser un entero mayor o igual a 0';
                }

                if ($ivaRaw !== '' && (!is_numeric($ivaRaw) || (float) $ivaRaw < 0 || (float) $ivaRaw > 100)) {
                    $rowErrors[] = 'iva_porcentaje debe estar entre 0 y 100';
                }

                if ($precioUnitarioRaw === '' || !is_numeric($precioUnitarioRaw) || (float) $precioUnitarioRaw < 0) {
                    $rowErrors[] = 'precio_unitario debe ser numerico mayor o igual a 0';
                }

                if ($codigoInterno !== '') {
                    if (isset($seenInternos[$codigoInterno])) {
                        $rowErrors[] = 'codigo_interno repetido dentro del archivo';
                    } elseif ($products->existsByCodigoInterno($codigoInterno)) {
                        $rowErrors[] = 'codigo_interno ya existe';
                    } else {
                        $seenInternos[$codigoInterno] = true;
                    }
                }

                if ($codigoBarras !== '') {
                    if (isset($seenBarras[$codigoBarras])) {
                        $rowErrors[] = 'codigo_barras repetido dentro del archivo';
                    } elseif ($products->existsByCodigoBarras($codigoBarras)) {
                        $rowErrors[] = 'codigo_barras ya existe';
                    } else {
                        $seenBarras[$codigoBarras] = true;
                    }
                }

                if (!empty($rowErrors)) {
                    $failedCount++;
                    $errors[] = 'Fila ' . $rowNumber . ': ' . implode(', ', $rowErrors);
                    continue;
                }

                try {
                    DB::transaction(function () use (
                        $service,
                        $priceService,
                        $nombre,
                        $descripcion,
                        $codigoBarras,
                        $codigoInterno,
                        $categoria,
                        $unidadMedida,
                        $stockMinimoRaw,
                        $ivaRaw,
                        $precioUnitarioRaw
                    ) {
                        $product = $service->create([
                            'nombre' => $nombre,
                            'descripcion' => $descripcion !== '' ? $descripcion : null,
                            'codigo_barras' => $codigoBarras !== '' ? $codigoBarras : null,
                            'codigo_interno' => $codigoInterno !== '' ? $codigoInterno : null,
                            'categoria' => $categoria !== '' ? $categoria : null,
                            'unidad_medida' => $unidadMedida,
                            'stock_minimo' => (int) $stockMinimoRaw,
                            'iva_porcentaje' => $ivaRaw !== '' ? (float) $ivaRaw : null,
                            'estado' => true,
                        ]);

                        $priceService->create([
                            'producto_id' => $product->id,
                            'precio_unitario' => (float) $precioUnitarioRaw,
                        ]);
                    });

                    $createdCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                    $errors[] = 'Fila ' . $rowNumber . ': ' . $e->getMessage();
                }
            }

            $errorLog = empty($errors)
                ? null
                : implode(PHP_EOL, array_slice($errors, 0, 300));

            $imports->markCompleted(
                $import->refresh(),
                totalRows: count($rows),
                processedRows: $processedRows,
                createdCount: $createdCount,
                failedCount: $failedCount,
                errorLog: $errorLog
            );
        } catch (\Throwable $e) {
            $imports->markFailed($import->refresh(), $e->getMessage());
            throw $e;
        }
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
