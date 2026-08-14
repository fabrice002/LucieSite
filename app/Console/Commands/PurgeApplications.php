<?php

namespace App\Console\Commands;

use App\Actions\PurgeExpiredApplications;
use App\Models\User;
use App\Notifications\ApplicationsNearingExpiry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class PurgeApplications extends Command
{
    protected $signature = 'ln:purge-applications {--dry-run : Affiche ce qui serait supprimé sans rien effacer}';

    protected $description = 'Efface définitivement les dossiers supprimés depuis 90 jours et les dossiers sans activité';

    public function handle(PurgeExpiredApplications $purge): int
    {
        $mois = (int) config('retention.months', 36);

        if ($this->option('dry-run')) {
            return $this->simuler($mois);
        }

        $this->preavis();

        $resultat = $purge();

        if ($resultat['dossiers'] === 0) {
            $this->info('Aucun dossier à purger.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d dossier(s) et %d fichier(s) supprimés définitivement.',
            $resultat['dossiers'],
            $resultat['fichiers'],
        ));

        $this->line(sprintf(
            '  %d supprimé(s) depuis plus de %d jours, %d sans activité depuis %d mois.',
            $resultat['supprimes'],
            PurgeExpiredApplications::RETENTION_DAYS,
            $resultat['inactifs'],
            $mois,
        ));

        return self::SUCCESS;
    }

    /**
     * Annonce sans rien effacer, les deux règles comprises.
     */
    private function simuler(int $mois): int
    {
        $supprimes = PurgeExpiredApplications::supprimesDepuisLongtemps()->count();
        $inactifs = PurgeExpiredApplications::inactifsDepuisLongtemps()->count();
        $bientot = PurgeExpiredApplications::bientotExpires()->count();

        $this->info(($supprimes + $inactifs).' dossier(s) seraient supprimés définitivement.');
        $this->line("  {$supprimes} supprimé(s) depuis plus de ".PurgeExpiredApplications::RETENTION_DAYS.' jours.');
        $this->line("  {$inactifs} sans activité depuis {$mois} mois.");

        if ($bientot > 0) {
            $this->newLine();
            $this->comment("{$bientot} dossier(s) arriveront à échéance dans les ".config('retention.notice_days').' prochains jours.');

            $this->table(
                ['Référence', 'Statut', 'Dernière activité'],
                PurgeExpiredApplications::bientotExpires()->get()
                    ->map(fn ($application): array => [
                        $application->reference,
                        $application->status->label(),
                        $application->lastActivityAt()->translatedFormat('j M Y'),
                    ])
                    ->all(),
            );
        }

        return self::SUCCESS;
    }

    /**
     * Prévient les administrateurs des dossiers sur le point d'être effacés,
     * afin qu'une exception reste possible.
     */
    private function preavis(): void
    {
        $bientot = PurgeExpiredApplications::bientotExpires()->get();

        if ($bientot->isEmpty()) {
            return;
        }

        $administrateurs = User::role('admin')->get();

        if ($administrateurs->isEmpty()) {
            return;
        }

        Notification::send($administrateurs, new ApplicationsNearingExpiry($bientot));

        $this->line($bientot->count().' dossier(s) arrivent à échéance : préavis envoyé aux administrateurs.');
    }
}
