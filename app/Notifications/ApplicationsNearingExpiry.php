<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Préavis : ces dossiers approchent du bout de leur conservation.
 *
 * Envoyé à J-30 puis à J-7. Aucun effacement n'est annoncé — à l'échéance, le
 * dossier entre simplement dans la file d'attente de décision.
 *
 * Le préavis sert à éviter d'y arriver : reprendre contact avec un candidat, ou
 * simplement intervenir sur le dossier, suffit à repousser l'échéance.
 */
class ApplicationsNearingExpiry extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Application>  $applications
     */
    public function __construct(public readonly Collection $applications) {}

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
        $preavis = (int) config('retention.notice_days', 30);
        $mois = (int) config('retention.months', 36);

        $message = (new MailMessage)
            ->subject($this->applications->count().' dossier(s) arriveront à échéance dans '.$preavis.' jours')
            ->line("Les dossiers suivants n'ont connu aucune activité depuis près de {$mois} mois.")
            ->line("Dans {$preavis} jours, ils entreront dans la file **« Dossiers arrivés à échéance ».** "
                .'Rien ne sera supprimé : il vous reviendra alors de les conserver douze mois de plus, '
                .'ou de les effacer.');

        foreach ($this->applications as $application) {
            $message->line(sprintf(
                '- **%s** — %s (%s), dernière activité le %s',
                $application->reference,
                $application->full_name,
                $application->status->label(),
                $application->lastActivityAt()->translatedFormat('j F Y'),
            ));
        }

        return $message
            ->line('Pour éviter qu\'un dossier n\'arrive à échéance, il suffit de l\'ouvrir dans le back-office et d\'y intervenir : toute activité repousse la date.')
            ->action('Ouvrir le back-office', url('/admin/applications'))
            ->salutation('LN Immigration');
    }
}
