<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ExportProductsExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $products,
        public string $storagePath,
        public array $filters = []
    ) {
    }

    public function handle(): void
    {
        Storage::disk('local')->makeDirectory(dirname($this->storagePath));
        Storage::disk('local')->put($this->storagePath, $this->buildExcelHtml());
    }

    private function buildExcelHtml(): string
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
        $html[] = ".muted{color:#334155;}";
        $html[] = '</style></head><body>';

        $html[] = '<table>';
        $html[] = '<tr>';
        $html[] = '<th>Nombre</th>';
        $html[] = '<th>Codigo interno</th>';
        $html[] = '<th>Codigo de barras</th>';
        $html[] = '<th>Categoria</th>';
        $html[] = '<th>Estado</th>';
        $html[] = '</tr>';

        if (empty($this->products)) {
            $html[] = '<tr><td colspan="5">No hay productos para los filtros seleccionados.</td></tr>';
        } else {
            foreach ($this->products as $product) {
                $isActive = (bool) ($product['estado'] ?? false);

                $html[] = '<tr>';
                $html[] = '<td>' . e($product['nombre'] ?? '-') . '</td>';
                $html[] = '<td>' . e($product['codigo_interno'] ?? '-') . '</td>';
                $html[] = '<td>' . e($product['codigo_barras'] ?? '-') . '</td>';
                $html[] = '<td>' . e($product['categoria'] ?? '-') . '</td>';
                $html[] = '<td>' . ($isActive ? 'Activo' : 'Inactivo') . '</td>';
                $html[] = '</tr>';
            }
        }

        $html[] = '</table>';
        $html[] = '</body></html>';

        return implode('', $html);
    }
}
