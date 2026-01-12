<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public $vendor;
    public $status;
    public $comment;

    /**
     * Create a new message instance.
     */
    public function __construct(User $vendor, string $status, ?string $comment = null)
    {
        $this->vendor = $vendor;
        $this->status = $status;
        $this->comment = $comment;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $statusTranslations = [
            'pending' => 'En attente',
            'active' => 'Actif',
            'inactive' => 'Inactif',
            'suspended' => 'Suspendu',
            'waiting' => 'En attente'
        ];

        $statusText = $statusTranslations[strtolower($this->status)] ?? ucfirst($this->status);

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo(config('mail.contact_address', config('mail.from.address')))
            ->subject('Mise à jour du statut de votre compte vendeur - ' . $statusText . ' - Dabablane')
            ->view('emails.vendor-status-changed')
            ->with([
                'vendor' => $this->vendor,
                'status' => $this->status,
                'comment' => $this->comment,
            ]);
    }
}
