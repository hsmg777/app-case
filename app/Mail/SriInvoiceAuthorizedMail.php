<?php

namespace App\Mail;

use App\Models\Sales\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SriInvoiceAuthorizedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Sale $sale,
        public $invoice,
        public string $ridePath,
        public string $xmlAutorizadoPath
    ) {}

    public function build()
    {
        return $this->subject('Factura electrónica autorizada')
            ->view('emails.sri.authorized')
            ->with([
                'sale' => $this->sale,
                'invoice' => $this->invoice,
            ])
            ->attachFromStorageDisk('local', $this->ridePath, 'RIDE.pdf', [
                'mime' => 'application/pdf',
            ])
            ->attachFromStorageDisk('local', $this->xmlAutorizadoPath, 'FACTURA.xml', [
                'mime' => 'application/xml',
            ]);
    }
}
