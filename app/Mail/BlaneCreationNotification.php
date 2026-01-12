<?php

namespace App\Mail;

use App\Models\Blane;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BlaneCreationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $blane;

    /**
     * Create a new message instance.
     */
    public function __construct(Blane $blane)
    {
        $this->blane = $blane;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Nouvelle Création de Blane - Action Requise - Dabablane')
            ->view('emails.blane-creation-notification');
    }
}
