<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Accusé de réception envoyé au candidat, avec sa référence de suivi.
 */
class ApplicationSubmitted extends Notification implements ShouldQueue
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
            ->subject('Votre dossier a bien été reçu — référence '.$this->application->reference)
            ->greeting('Bonjour '.$this->application->first_name.',')
            ->line('Nous avons bien reçu votre dossier et nous vous en remercions.')
            ->line('Votre référence de suivi est : **'.$this->application->reference.'**')
            ->line('Conservez-la : elle vous sera demandée pour consulter l\'avancement de votre dossier.')
            ->action('Suivre mon dossier', route('suivi.index'))
            ->line('Notre équipe revient vers vous dès que votre dossier a été étudié.')
            ->salutation('L\'équipe LN Immigration');
    }
}
