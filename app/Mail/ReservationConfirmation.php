<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Reservation;

class ReservationConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;

    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    public function build()
    {
        return $this->from('contact@dabablane.com', 'Dabablane')
                    ->view('emails.reservation-confirmation')
                    ->subject('Confirmation de Réservation - ' . $this->reservation->NUM_RES);
    }
}
