<?php

namespace App\Jobs;

use App\Mail\SriInvoiceAuthorizedMail;
use App\Models\Sales\Sale;
use App\Repositories\Sri\ElectronicInvoiceRepository;
use App\Services\Sri\RideService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class SendSriInvoiceMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $saleId) {}

    public function handle(
        RideService $rideService,
        ElectronicInvoiceRepository $repo
    ): void {
        $sale = Sale::with(['client'])->findOrFail($this->saleId);
        $invoice = $repo->findBySaleId($sale->id);

        if (!$invoice || strtoupper((string)($invoice->estado_sri ?? '')) !== 'AUTORIZADO') {
            return;
        }

        $xmlPath = (string)($invoice->xml_autorizado_path ?? '');
        if ($xmlPath === '' || !Storage::disk('local')->exists($xmlPath)) {
            return;
        }

        $ridePath = (string)($invoice->ride_pdf_path ?? '');
        if ($ridePath === '' || !Storage::disk('local')->exists($ridePath)) {
            $ridePath = (string) $rideService->generateForSale($sale->id);
        }

        if ($ridePath === '' || !Storage::disk('local')->exists($ridePath)) {
            return;
        }

        $to = (string)($sale->email_destino ?? '');
        if ($to === '') {
            $to = (string)($sale->client->email ?? '');
        }
        if ($to === '') return;

        try {
            Mail::to($to)->send(new SriInvoiceAuthorizedMail(
                $sale,
                $invoice,
                $ridePath,
                $xmlPath
            ));
        } catch (\Throwable $e) {
            Log::error('SendSriInvoiceMailJob FAIL', [
                'sale_id' => $sale->id,
                'to' => $to,
                'ridePath' => $ridePath,
                'xmlPath' => $xmlPath,
                'error' => $e->getMessage(),
            ]);
            throw $e; // para que quede en failed_jobs con el error real
        }
    }
}
