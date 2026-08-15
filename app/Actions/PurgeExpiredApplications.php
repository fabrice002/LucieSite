<?php

namespace App\Actions;

use App\Enums\RetentionState;
use App\Models\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Conservation des dossiers — aucune suppression sans décision humaine.
 *
 * Un dossier contient des scans de passeports et de diplômes. Les détruire
 * parce que personne n'a rien fait pendant trois ans reviendrait à faire
 * reposer une destruction irréversible sur un silence. Ce n'est pas acceptable.
 *
 * Le déroulement est donc en deux temps :
 *
 *   1. À l'échéance, le dossier passe **en attente de décision**. Rien n'est
 *      supprimé, les fichiers restent intacts. Il apparaît dans une file du
 *      back-office, et les administrateurs sont relancés jusqu'à ce qu'ils
 *      tranchent : conserver douze mois de plus, ou effacer.
 *
 *   2. L'effacement ne concerne que deux catégories, toutes deux issues d'un
 *      acte humain : les dossiers **supprimés** depuis plus de 90 jours, et
 *      ceux qu'un administrateur a **explicitement marqués** pour effacement.
 *
 * Le revers est assumé : si personne ne traite la file, des passeports
 * resteront stockés. C'est pourquoi le bandeau du tableau de bord ne se ferme
 * pas et le rappel mensuel ne s'arrête jamais.
 */
class PurgeExpiredApplications
{
    /** Durée de rétention après suppression, en jours. */
    public const RETENTION_DAYS = 90;

    /** Durée du sursis accordé par « Conserver 12 mois de plus », en mois. */
    public const SURSIS_MOIS = 12;

    /**
     * @return array{dossiers: int, fichiers: int, supprimes: int, marques: int, en_attente: int}
     */
    public function __invoke(?Carbon $before = null): array
    {
        $resultat = ['dossiers' => 0, 'fichiers' => 0, 'supprimes' => 0, 'marques' => 0, 'en_attente' => 0];

        // D'abord signaler : un dossier qui arrive à échéance aujourd'hui entre
        // dans la file, il n'est pas effacé dans la foulée.
        $resultat['en_attente'] = $this->signalerLesEchus();

        $this->effacer(self::supprimesDepuisLongtemps($before), $resultat, 'supprimes');
        $this->effacer(self::marquesPourEffacement(), $resultat, 'marques');

        return $resultat;
    }

    /**
     * Fait basculer en attente de décision les dossiers arrivés à échéance.
     *
     * Aucun fichier n'est touché. C'est le seul effet de cette étape.
     */
    public function signalerLesEchus(): int
    {
        $signales = 0;

        self::echus()->chunkById(100, function (Collection $dossiers) use (&$signales): void {
            foreach ($dossiers as $application) {
                // timestamps désactivés : marquer un dossier ne doit pas passer
                // pour une activité, sinon l'échéance se repousserait seule et
                // le dossier ne serait jamais présenté à une décision.
                $application->timestamps = false;
                $application->retention_state = RetentionState::EnAttenteDeDecision;
                $application->save();

                activity('dossier')
                    ->performedOn($application)
                    ->withProperties(['reference' => $application->reference])
                    ->log('Dossier arrivé à échéance, en attente de décision');

                $signales++;
            }
        });

        return $signales;
    }

    /**
     * Efface un seul dossier, immédiatement.
     *
     * Employé par la décision prise depuis le back-office : l'administratrice
     * clique, le dossier disparaît. Même chemin de code que la commande
     * planifiée — un seul endroit sait effacer des fichiers.
     *
     * @return array{dossiers: int, fichiers: int, supprimes: int, marques: int, en_attente: int}
     */
    public function effacerCeDossier(Application $application): array
    {
        $resultat = ['dossiers' => 0, 'fichiers' => 0, 'supprimes' => 0, 'marques' => 0, 'en_attente' => 0];

        $this->effacer(
            Application::query()->withTrashed()->whereKey($application->getKey()),
            $resultat,
            'marques',
        );

        return $resultat;
    }

    /**
     * Dossiers dont l'échéance est atteinte et qui n'ont pas encore de suite.
     *
     * @return Builder<Application>
     */
    public static function echus(?Carbon $maintenant = null): Builder
    {
        return Application::query()
            ->whereNull('retention_state')
            ->whereNotNull('retention_due_at')
            ->where('retention_due_at', '<=', $maintenant ?? now());
    }

    /**
     * La file d'attente : les dossiers qui réclament une décision.
     *
     * @return Builder<Application>
     */
    public static function enAttenteDeDecision(): Builder
    {
        return Application::query()
            ->where('retention_state', RetentionState::EnAttenteDeDecision)
            ->orderBy('retention_due_at');
    }

    /**
     * Dossiers qu'un administrateur a demandé d'effacer.
     *
     * @return Builder<Application>
     */
    public static function marquesPourEffacement(): Builder
    {
        return Application::query()->where('retention_state', RetentionState::MarquePourEffacement);
    }

    /**
     * Dossiers supprimés dont le délai de rétention est écoulé.
     *
     * @return Builder<Application>
     */
    public static function supprimesDepuisLongtemps(?Carbon $before = null): Builder
    {
        $before ??= now()->subDays(self::RETENTION_DAYS);

        return Application::onlyTrashed()->where('deleted_at', '<=', $before);
    }

    /**
     * Dossiers qui arriveront à échéance dans la fenêtre indiquée.
     *
     * Sert aux rappels de J-30 et J-7 : la fenêtre couvre une journée, et la
     * commande tourne une fois par jour.
     *
     * @return Builder<Application>
     */
    public static function echeanceDansJours(int $jours): Builder
    {
        return Application::query()
            ->whereNull('retention_state')
            ->whereNotNull('retention_due_at')
            ->whereBetween('retention_due_at', [
                now()->addDays($jours)->startOfDay(),
                now()->addDays($jours)->endOfDay(),
            ]);
    }

    /**
     * Dossiers en attente dont le dernier rappel remonte à plus d'un mois.
     *
     * Jamais de fin : tant qu'aucune décision n'est prise, la relance revient.
     *
     * @return Builder<Application>
     */
    public static function aRelancer(): Builder
    {
        return self::enAttenteDeDecision()
            ->where(fn (Builder $query) => $query
                ->whereNull('retention_reminded_at')
                ->orWhere('retention_reminded_at', '<=', now()->subMonth()));
    }

    /**
     * @param  Builder<Application>  $query
     * @param  array{dossiers: int, fichiers: int, supprimes: int, marques: int, en_attente: int}  $resultat
     */
    private function effacer(Builder $query, array &$resultat, string $categorie): void
    {
        $disk = Storage::disk(SubmitApplication::DISK);

        $query->with('documents')->chunkById(100, function (Collection $expires) use ($disk, &$resultat, $categorie): void {
            foreach ($expires as $application) {
                foreach ($application->documents as $document) {
                    if ($disk->exists($document->path)) {
                        $disk->delete($document->path);
                        $resultat['fichiers']++;
                    }
                }

                // Le dossier du candidat sur le disque, désormais vide.
                $disk->deleteDirectory('documents/'.$application->reference);

                // La seule trace qui subsiste est celle du journal d'activité.
                activity('dossier')
                    ->withProperties([
                        'reference' => $application->reference,
                        'motif' => $categorie === 'supprimes'
                            ? 'Supprimé depuis plus de '.self::RETENTION_DAYS.' jours'
                            : 'Effacement décidé par un administrateur',
                    ])
                    ->log('Dossier effacé définitivement');

                // forceDelete supprime les lignes documents en cascade.
                $application->forceDelete();

                $resultat['dossiers']++;
                $resultat[$categorie]++;
            }
        });
    }
}
