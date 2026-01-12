<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Order;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        return $this->from('contact@dabablane.com', 'Dabablane')
                    ->view('emails.order-confirmation')
                    ->subject('Confirmation de Commande - ' . $this->order->NUM_ORD);
    }
}
