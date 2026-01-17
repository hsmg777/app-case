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

use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessSriInvoiceJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 15;
    public int $uniqueFor = 900; // Unicidad por 15 min

    public function uniqueId(): string
    {
        return 'sale:' . $this->saleId;
    }

    // Reintentos con backoff (segundos)
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public int $timeout = 120;

    public function __construct(public int $saleId)
    {
    }

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

            // Retorna array ['status' => ..., 'invoice' => ...]
            $result = $sri->sendAndAuthorizeForSale($sale->id);

            $status = $result['status'] ?? '';
            $inv = $result['invoice'] ?? null; // Puede ser null si rejected en recepcion

            // 1. Si está en procesamiento (70 o timeout), soltamos el job
            if ($status === 'PROCESSING') {
                $this->release(30);
                return;
            }

            // 2. Si autorizado, guardamos y fin
            if ($status === 'AUTHORIZED') {
                SendSriInvoiceMailJob::dispatch($sale->id);
                return;
            }

            // 3. Si sigue enviado sin respuesta, reintentamos
            if ($status === 'ENVIADO') {
                // Aun no autorizado? Release
                $this->release(30);
                return;
            }

            // 4. Si rechazado, lanzamos excepcion para loguear o dejar failed si se acaban intentos?
            // User dijo: "pase a RECHAZADO real por validación".
            // Si es rechazado real, NO deberíamos reintentar infinitamente.
            if ($status === 'REJECTED') {
                // Logueamos y terminamos el job (no tiramos excepcion para no reintentar)
                // Ojo: si hay un error transitorio que lo marcó rejected (raro), se pierde.
                // Pero SRI rejected suele ser definitivo (XML mal formados, datos invalidos).
                \Log::warning("SRI Rechazado Final Sale #{$sale->id}", ['result' => $result]);
                return;
            }

        } catch (\Throwable $e) {
            \Log::error('SRI flow failed (job)', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);

            // Marca error SOLO en electronic_invoices (no en sales)
            $sri->markInvoiceError($sale->id, $e->getMessage());

            // Relanza para que el job reintente y si se agotan intentos, quede en failed_jobs
            throw $e;
        }
    }
}
