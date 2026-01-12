<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Purchase;

class SubscriptionExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public $purchase;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Purchase $purchase)
    {
        $this->purchase = $purchase;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Your Subscription Has Expired - Renew Now')
            ->view('emails.subscription-expired')
            ->with([
                'purchase' => $this->purchase,
                'user' => $this->purchase->user,
                'plan' => $this->purchase->plan,
            ]);
    }
}

