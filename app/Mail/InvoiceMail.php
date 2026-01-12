<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Configuration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $purchase;
    public $invoice;
    public $config;

    public function __construct(Purchase $purchase, Invoice $invoice, Configuration $config = null)
    {
        $this->purchase = $purchase;
        $this->invoice = $invoice;
        $this->config = $config ?? new Configuration([
            'billing_email' => 'contact@dabablane.com',
            'contact_email' => 'contact@dabablane.com',
            'contact_phone' => '+212615170064',
            'invoice_prefix' => 'DABA-INV-',
        ]);
    }

    public function build()
    {
        $email = $this->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo($this->config->billing_email, 'Service Facturation')
            ->subject('Votre facture #' . $this->invoice->invoice_number)
            ->markdown('emails.invoice');

        $pdfFullPath = storage_path('app/public/' . $this->invoice->pdf_path);

        if (file_exists($pdfFullPath)) {
            $email->attach($pdfFullPath, [
                'as' => 'facture-' . $this->invoice->invoice_number . '.pdf',
                'mime' => 'application/pdf',
            ]);
        } else {
            \Log::warning('PDF not found for attachment: ' . $pdfFullPath);
        }


        return $email;
    }
}