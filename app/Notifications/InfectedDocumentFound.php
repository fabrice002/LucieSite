<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient les administrateurs qu'une pièce déposée est infectée.
 *
 * Le fichier n'est jamais joint, évidemment, et le dossier est passé au statut
 * « incomplet » afin qu'il ne soit pas traité comme les autres.
 */
class InfectedDocumentFound extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Document $document) {}

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
        $application = $this->document->application;

        return (new MailMessage)
            ->error()
            ->subject('Fichier infecté détecté — dossier '.$application->reference)
            ->line('L\'analyse antivirus a détecté un fichier infecté dans un dossier déposé.')
            ->line('Référence : **'.$application->reference.'**')
            ->line('Candidat : '.$application->full_name)
            ->line('Pièce concernée : '.$this->document->original_name.' ('.$this->document->type->label().')')
            ->line('Le téléchargement de cette pièce est bloqué et le dossier est passé au statut « Dossier incomplet ».')
            ->line('**N\'essayez pas de récupérer ce fichier.** Contactez le candidat pour qu\'il en dépose une nouvelle version.')
            ->salutation('LN Immigration');
    }
}
