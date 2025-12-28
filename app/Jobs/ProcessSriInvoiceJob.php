<?php

namespace App\Jobs;

use App\Models\Sales\Sale;
use App\Services\Sri\SriInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use App\Jobs\SendSriInvoiceMailJob;

class ProcessSriInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    // Reintentos con backoff (segundos)
    public function backoff(): array
    {
        return [60, 180, 600, 1200, 1800];
    }

    public int $timeout = 120;

    public function __construct(public int $saleId) {}

    public function handle(SriInvoiceService $sri): void
    {
        $sale = Sale::findOrFail($this->saleId);

        // Si ya existe invoice AUTORIZADO, no reprocesar
        $existing = $sri->getInvoiceBySaleId($sale->id); // te dejo abajo el método
        if ($existing && Str::upper((string) $existing->estado_sri) === 'AUTORIZADO') {
            SendSriInvoiceMailJob::dispatch($sale->id);
            return;
        }

        try {
            $sri->generateXmlForSale($sale->id);
            $sri->signXmlForSale($sale->id);

            $inv = $sri->sendAndAuthorizeForSale($sale->id);

            // Si autorizado, recién envías correo
            if (Str::upper((string)($inv->estado_sri ?? '')) === 'AUTORIZADO') {
                SendSriInvoiceMailJob::dispatch($sale->id);
            }

            // Si queda ENVIADO / SIN_RESPUESTA, lanzamos excepción para reintento
            // (opcional, pero recomendado para que vuelva a consultar autorización)
            if (in_array(Str::upper((string)($inv->estado_sri ?? '')), ['ENVIADO'], true)) {
                throw new \RuntimeException('SRI aún no responde autorización (ENVIADO). Reintentando...');
            }

        } catch (\Throwable $e) {
            \Log::error('SRI flow failed (job)', [
                'sale_id' => $sale->id,
                'error'   => $e->getMessage(),
            ]);

            // Marca error SOLO en electronic_invoices (no en sales)
            $sri->markInvoiceError($sale->id, $e->getMessage());

            // Relanza para que el job reintente y si se agotan intentos, quede en failed_jobs
            throw $e;
        }
    }
}
