<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $vendor;
    public $password;

    /**
     * Create a new message instance.
     */
    public function __construct(User $vendor, string $password)
    {
        $this->vendor = $vendor;
        $this->password = $password;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo(config('mail.contact_address', config('mail.from.address')))
            ->subject('Vos identifiants de connexion vendeur - Dabablane')
            ->view('emails.vendor-credentials')
            ->with([
                'vendor' => $this->vendor,
                'password' => $this->password,
            ]);
    }
}












