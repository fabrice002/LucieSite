<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Préavis d'effacement : ces dossiers arrivent au bout de leur conservation.
 *
 * Le rapport laisse au cabinet le temps de faire une exception — reprendre
 * contact avec un candidat, ou simplement rouvrir le dossier, ce qui suffit à
 * en repousser l'échéance.
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
            ->subject($this->applications->count().' dossier(s) seront effacés dans '.$preavis.' jours')
            ->line("Les dossiers suivants n'ont connu aucune activité depuis {$mois} mois.")
            ->line("Ils seront **définitivement effacés**, fichiers compris, d'ici {$preavis} jours.");

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
            ->line('Pour conserver l\'un d\'eux, il suffit de l\'ouvrir dans le back-office et d\'y intervenir : toute activité repousse l\'échéance.')
            ->action('Ouvrir le back-office', url('/admin/applications'))
            ->salutation('LN Immigration');
    }
}
