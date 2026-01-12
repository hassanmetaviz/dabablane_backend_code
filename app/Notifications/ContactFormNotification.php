<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Contact;

class ContactFormNotification extends Notification
{
    use Queueable;

    public $contact;

    /**
     * Create a new notification instance.
     *
     * @param Contact $contact
     */
    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
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
            ->subject('New Contact Form Submission - Dabablane')
            ->view('emails.contact-form-submission', ['contact' => $this->contact])
            ->line('You have received a new contact form submission.')
            ->action('View Message', url('/admin/contacts/' . $this->contact->id))
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
            'contact_id' => $this->contact->id,
            'sender_name' => $this->contact->fullName ?? $this->contact->name ?? 'Anonymous',
            'sender_email' => $this->contact->email,
            'sender_phone' => $this->contact->phone ?? 'N/A',
            'subject' => $this->contact->subject,
            'type' => $this->contact->type ?? 'client',
            'status' => $this->contact->status ?? 'pending',
            'created_at' => $this->contact->created_at->toDateString(),
            'message' => 'New contact form submission from "' . ($this->contact->fullName ?? 'Anonymous') . '"',
        ];
    }
}

