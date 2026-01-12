<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Blane;

class BlaneCreationNotification extends Notification
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
        // Only use database channel for notifications
        // Email is sent separately in the controller
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
            ->subject('Nouvelle Création de Blane - Action Requise - Dabablane')
            ->view('emails.blane-creation-notification', ['blane' => $this->blane])
            ->line('Un nouveau Blane a été créé.')
            ->action('Voir le Blane', url('/admin/blanes/' . $this->blane->id))
            ->line('Merci d\'utiliser notre plateforme!');
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
            'vendor_name' => $this->blane->vendor->name ?? 'N/A',
            'category_id' => $this->blane->categories_id,
            'price' => $this->blane->prix_par_personne,
            'created_at' => $this->blane->created_at->toDateString(),
            'message' => 'Un nouveau Blane "' . $this->blane->name . '" a été créé.',
        ];
    }
}

