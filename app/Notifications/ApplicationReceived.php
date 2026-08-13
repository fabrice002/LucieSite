<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte interne : un nouveau dossier vient d'être déposé.
 */
class ApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Application $application) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouveau dossier déposé — '.$this->application->reference)
            ->line('Un nouveau dossier vient d\'être déposé sur le site.')
            ->line('Référence : '.$this->application->reference)
            ->line('Candidat : '.$this->application->full_name)
            ->line('Adresse e-mail : '.$this->application->email)
            ->line('Téléphone : '.$this->application->phone)
            ->line('Pays de résidence : '.$this->application->country_of_residence)
            ->line('Documents transmis : '.$this->application->documents()->count())
            ->salutation('LN Immigration');
    }
}
