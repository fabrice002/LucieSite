<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerte le candidat que son dossier a bougé.
 *
 * Cet e-mail est volontairement pauvre : référence, statut, et une invitation
 * à consulter la page de suivi. Il ne contient jamais le message rédigé par le
 * cabinet, aucune pièce jointe, et aucun lien de connexion automatique — le
 * candidat devra ressaisir sa référence et son adresse e-mail.
 */
class ApplicationStatusChanged extends Notification implements ShouldQueue
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
        $reference = $this->application->reference;
        $statut = $this->application->status->label();

        return (new MailMessage)
            ->subject(str_replace(
                ':reference',
                $reference,
                content('email_suivi.objet', 'Votre dossier :reference a été mis à jour'),
            ))
            ->greeting(str_replace(
                ':prenom',
                $this->application->first_name,
                content('email_suivi.salutation', 'Bonjour :prenom,'),
            ))
            ->line(content('email_suivi.intro', 'L\'état de votre dossier vient d\'évoluer.'))
            ->line(content('email_suivi.ligne_reference', 'Référence :').' **'.$reference.'**')
            ->line(content('email_suivi.ligne_statut', 'Nouvel état :').' **'.$statut.'**')
            ->line(content('email_suivi.invitation', 'Connectez-vous à la page de suivi avec votre référence et votre adresse e-mail pour consulter le détail.'))
            ->action(content('email_suivi.bouton', 'Consulter mon dossier'), route('suivi.index'))
            ->line(content('email_suivi.rappel_securite', 'Par sécurité, aucun détail de votre dossier n\'est transmis par e-mail.'))
            ->salutation(content('email_suivi.signature', 'L\'équipe LN Immigration'));
    }
}
