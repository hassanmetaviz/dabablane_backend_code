<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Blane;

class BlaneExpirationNotification extends Notification
{
    use Queueable;

    public $blane;

    /**
     * Create a new notification instance.
     *
     * @param Blane $blane
     */
    public function __construct(Blane $blane)
    {
        $this->blane = $blane;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Blane Expiration Alert')
            ->line('The Blane "' . $this->blane->name . '" is expired.')
            ->line('Expiration Date: ' . $this->blane->expiration_date)
            ->action('View Blane', url('/blanes/' . $this->blane->id))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'blane_id' => $this->blane->id,
            'blane_name' => $this->blane->name,
            'expiration_date' => $this->blane->expiration_date,
            'message' => 'The Blane "' . $this->blane->name . '" is expired.',
        ];
    }
}
