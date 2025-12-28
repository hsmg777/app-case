<?php

namespace App\Services\Sri;

use App\Models\Sales\Sale;
use App\Models\Sri\SriConfig;
use App\Repositories\Sri\ElectronicInvoiceRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RideService
{
    public function __construct(
        private SriConfigService $configService,
        private ElectronicInvoiceRepository $repo
    ) {}

    public function generateForSale(int $saleId): string
    {
        $sale = Sale::with([
            'items',
            'client',
            'payments.paymentMethod',
        ])->findOrFail($saleId);

        $invoice = $this->repo->findBySaleId($sale->id);

        if (!$invoice) {
            throw ValidationException::withMessages([
                'sri' => 'No existe electronic_invoice para esta venta.',
            ]);
        }

        if (strtoupper((string)($invoice->estado_sri ?? '')) !== 'AUTORIZADO') {
            throw ValidationException::withMessages([
                'sri' => 'La factura no está AUTORIZADA, no se puede generar RIDE.',
            ]);
        }

        $cfg = $this->configService->get();
        if (!$cfg instanceof SriConfig) {
            throw ValidationException::withMessages([
                'sri' => 'No existe configuración SRI.',
            ]);
        }

        $clave = (string)($invoice->clave_acceso ?? '');
        if ($clave === '') {
            throw ValidationException::withMessages([
                'sri' => 'Falta clave_acceso en electronic_invoices.',
            ]);
        }

        $pdf = Pdf::loadView('sri.ride.factura', [
            'sale' => $sale,
            'invoice' => $invoice,
            'cfg' => $cfg,
        ])->setPaper('A4', 'portrait');

        $dir = "sri/ride";
        Storage::disk('local')->makeDirectory($dir);

        $path = "{$dir}/{$clave}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        // recomendado: guardar ruta
        $invoice->ride_pdf_path = $path;
        $invoice->save();

        return $path;
    }
}
