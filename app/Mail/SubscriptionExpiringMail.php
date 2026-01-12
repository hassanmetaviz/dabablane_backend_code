<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Purchase;

class SubscriptionExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public $purchase;
    public $daysRemaining;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Purchase $purchase, $daysRemaining)
    {
        $this->purchase = $purchase;
        $this->daysRemaining = $daysRemaining;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Your Subscription is Expiring Soon - Action Required')
            ->view('emails.subscription-expiring')
            ->with([
                'purchase' => $this->purchase,
                'daysRemaining' => $this->daysRemaining,
                'user' => $this->purchase->user,
                'plan' => $this->purchase->plan,
            ]);
    }
}

