<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Rappel : ces dossiers attendent une décision, et l'attendront indéfiniment.
 *
 * Rien n'a été supprimé et rien ne le sera sans un clic. Le revers est que ces
 * scans de passeports restent stockés tant que personne ne tranche — d'où une
 * relance qui revient tous les mois, sans fin et sans possibilité de la couper.
 */
class ApplicationsAwaitingDecision extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Application>  $applications
     */
    public function __construct(public readonly Collection $applications) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nombre = $this->applications->count();

        $message = (new MailMessage)
            ->subject($nombre.' dossier(s) attendent une décision de conservation')
            ->line('Les dossiers suivants ont dépassé leur durée de conservation.')
            ->line('**Rien n\'a été supprimé.** Les pièces sont intactes et le resteront tant qu\'une décision n\'aura pas été prise.');

        foreach ($this->applications as $application) {
            $message->line(sprintf(
                '- **%s** — %s (%s), échéance atteinte le %s',
                $application->reference,
                $application->full_name,
                $application->status->label(),
                $application->retention_due_at?->translatedFormat('j F Y') ?? '—',
            ));
        }

        return $message
            ->line('Deux issues possibles pour chacun : le conserver douze mois de plus, ou l\'effacer définitivement.')
            ->line('Tant que vous ne choisissez pas, ces scans de pièces d\'identité restent stockés — et ce rappel reviendra chaque mois.')
            ->action('Traiter les dossiers en attente', url('/admin/dossiers-en-attente'))
            ->salutation('LN Immigration');
    }
}
