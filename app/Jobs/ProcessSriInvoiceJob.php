<?php

namespace App\Jobs;

use App\Models\Sales\Sale;
use App\Services\Sri\SriInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSriInvoiceJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 20;
    public int $uniqueFor = 1800;
    public int $timeout = 180;

    public function __construct(public int $saleId)
    {
    }

    public function uniqueId(): string
    {
        return 'sale:' . $this->saleId;
    }

    public function backoff(): array
    {
        return [15, 30, 60, 120, 240, 300];
    }

    public function handle(SriInvoiceService $sri): void
    {
        $sale = Sale::findOrFail($this->saleId);

        $result = $sri->processSaleInvoice($sale->id);

        $status = strtoupper((string) ($result['status'] ?? ''));

        if ($status === 'AUTORIZADO') {
            SendSriInvoiceMailJob::dispatch($sale->id);
            return;
        }

        if ($status === 'PROCESSING') {
            $this->release(60);
            return;
        }

        if (in_array($status, ['RECHAZADO', 'REJECTED'], true)) {
            \Log::warning("SRI: Rechazado final Sale #{$sale->id}", ['result' => $result]);
            return;
        }

        // ERROR u otro estado inesperado => que reintente por tries/backoff
        $msg = (string) ($result['message'] ?? 'SRI: error desconocido');
        throw new \RuntimeException($msg);
    }
}
