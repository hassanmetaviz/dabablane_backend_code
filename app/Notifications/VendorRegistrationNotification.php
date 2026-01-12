<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\User;

class VendorRegistrationNotification extends Notification
{
    use Queueable;

    public $vendor;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $vendor)
    {
        $this->vendor = $vendor;
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
            ->subject('Nouvelle Inscription Vendeur - Action Requise - Dabablane')
            ->view('emails.vendor-registration-notification', ['vendor' => $this->vendor])
            ->line('Un nouveau vendeur vient de s\'inscrire sur la plateforme.')
            ->action('Voir le vendeur', url('/admin/vendors/' . $this->vendor->id))
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
            'vendor_id' => $this->vendor->id,
            'vendor_name' => $this->vendor->name,
            'company_name' => $this->vendor->company_name ?? 'N/A',
            'email' => $this->vendor->email,
            'phone' => $this->vendor->phone ?? 'N/A',
            'status' => $this->vendor->status ?? 'pending',
            'message' => 'Un nouveau vendeur "' . $this->vendor->name . '" vient de s\'inscrire.',
        ];
    }
}

