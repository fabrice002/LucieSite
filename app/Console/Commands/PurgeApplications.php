<?php

namespace App\Console\Commands;

use App\Actions\PurgeExpiredApplications;
use App\Models\Application;
use App\Models\User;
use App\Notifications\ApplicationsAwaitingDecision;
use App\Notifications\ApplicationsNearingExpiry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Entretien quotidien de la conservation.
 *
 * Cette commande n'efface plus jamais un dossier au seul motif qu'il est
 * ancien. Elle le signale, relance les administrateurs, et n'efface que ce
 * qu'un humain a explicitement décidé d'effacer.
 */
class PurgeApplications extends Command
{
    protected $signature = 'ln:purge-applications {--dry-run : Affiche ce qui se passerait sans rien modifier}';

    protected $description = 'Signale les dossiers arrivés à échéance et efface ceux dont l\'effacement a été décidé';

    public function handle(PurgeExpiredApplications $purge): int
    {
        if ($this->option('dry-run')) {
            return $this->simuler();
        }

        $this->preavis();

        $resultat = $purge();

        if ($resultat['en_attente'] > 0) {
            $this->warn($resultat['en_attente'].' dossier(s) sont arrivés à échéance et attendent une décision. Rien n\'a été supprimé.');
        }

        $this->relancer();

        if ($resultat['dossiers'] === 0) {
            $this->info('Aucun dossier effacé.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d dossier(s) et %d fichier(s) effacés définitivement.',
            $resultat['dossiers'],
            $resultat['fichiers'],
        ));

        $this->line(sprintf(
            '  %d supprimé(s) depuis plus de %d jours, %d dont l\'effacement avait été décidé.',
            $resultat['supprimes'],
            PurgeExpiredApplications::RETENTION_DAYS,
            $resultat['marques'],
        ));

        return self::SUCCESS;
    }

    /**
     * Annonce ce qui se passerait, sans rien modifier.
     */
    private function simuler(): int
    {
        $echus = PurgeExpiredApplications::echus()->get();
        $enAttente = PurgeExpiredApplications::enAttenteDeDecision()->count();
        $supprimes = PurgeExpiredApplications::supprimesDepuisLongtemps()->count();
        $marques = PurgeExpiredApplications::marquesPourEffacement()->count();

        $this->info($echus->count().' dossier(s) basculeraient en attente de décision. Aucun ne serait supprimé.');

        if ($echus->isNotEmpty()) {
            $this->table(
                ['Référence', 'Statut', 'Échéance'],
                $echus->map(fn (Application $application): array => [
                    $application->reference,
                    $application->status->label(),
                    $application->retention_due_at?->translatedFormat('j M Y') ?? '—',
                ])->all(),
            );
        }

        $this->newLine();
        $this->line(($supprimes + $marques).' dossier(s) seraient effacés définitivement :');
        $this->line("  {$supprimes} supprimé(s) depuis plus de ".PurgeExpiredApplications::RETENTION_DAYS.' jours.');
        $this->line("  {$marques} dont un administrateur a demandé l'effacement.");

        if ($enAttente > 0) {
            $this->newLine();
            $this->comment("{$enAttente} dossier(s) attendent déjà une décision.");
        }

        return self::SUCCESS;
    }

    /**
     * Préavis à J-30 et J-7, avant que l'échéance ne soit atteinte.
     */
    private function preavis(): void
    {
        $preavis = (int) config('retention.notice_days', 30);

        foreach ([$preavis, 7] as $jours) {
            $concernes = PurgeExpiredApplications::echeanceDansJours($jours)->get();

            if ($concernes->isEmpty()) {
                continue;
            }

            $this->notifierLesAdministrateurs(new ApplicationsNearingExpiry($concernes));

            $this->line($concernes->count()." dossier(s) arrivent à échéance dans {$jours} jours : préavis envoyé.");
        }
    }

    /**
     * Relance mensuelle, tant que la file n'est pas vide.
     */
    private function relancer(): void
    {
        $aRelancer = PurgeExpiredApplications::aRelancer()->get();

        if ($aRelancer->isEmpty()) {
            return;
        }

        $this->notifierLesAdministrateurs(new ApplicationsAwaitingDecision($aRelancer));

        foreach ($aRelancer as $application) {
            // Sans désactiver les timestamps, envoyer un rappel passerait pour
            // une activité et repousserait l'échéance qu'il signale.
            $application->timestamps = false;
            $application->retention_reminded_at = now();
            $application->save();
        }

        $this->line($aRelancer->count().' dossier(s) en attente : rappel envoyé aux administrateurs.');
    }

    /**
     * @param  ApplicationsNearingExpiry|ApplicationsAwaitingDecision  $notification
     */
    private function notifierLesAdministrateurs(object $notification): void
    {
        /** @var Collection<int, User> $administrateurs */
        $administrateurs = User::role('admin')->get();

        if ($administrateurs->isEmpty()) {
            return;
        }

        Notification::send($administrateurs, $notification);
    }
}
