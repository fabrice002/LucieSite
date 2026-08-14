<?php

namespace App\Actions;

use App\Models\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Efface définitivement les dossiers arrivés au bout de leur conservation.
 *
 * Deux règles, distinctes :
 *
 *   1. Les dossiers **supprimés** depuis plus de 90 jours. La suppression douce
 *      laisse les scans sur le disque : passé ce délai, ils disparaissent.
 *
 *   2. Les dossiers **vivants mais abandonnés**, sans aucune activité depuis
 *      config('retention.months') mois. Sans cette règle, un dossier au statut
 *      « validé » conserverait un passeport indéfiniment.
 *
 * L'activité couvre le dépôt, toute modification du dossier et tout message
 * adressé au candidat : un dossier réellement suivi n'est jamais purgé.
 */
class PurgeExpiredApplications
{
    /** Durée de rétention après suppression, en jours. */
    public const RETENTION_DAYS = 90;

    /**
     * @return array{dossiers: int, fichiers: int, supprimes: int, inactifs: int}
     */
    public function __invoke(?Carbon $before = null): array
    {
        $resultat = ['dossiers' => 0, 'fichiers' => 0, 'supprimes' => 0, 'inactifs' => 0];

        $this->effacer(
            self::supprimesDepuisLongtemps($before),
            $resultat,
            'supprimes',
        );

        $this->effacer(
            self::inactifsDepuisLongtemps(),
            $resultat,
            'inactifs',
        );

        return $resultat;
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
     * Dossiers vivants sans aucune activité depuis la durée de conservation.
     *
     * @return Builder<Application>
     */
    public static function inactifsDepuisLongtemps(?Carbon $before = null): Builder
    {
        $before ??= now()->subMonths((int) config('retention.months', 36));

        return Application::query()
            ->where('updated_at', '<=', $before)
            ->whereDoesntHave('updates', fn (Builder $query) => $query->where('created_at', '>', $before));
    }

    /**
     * Dossiers qui atteindront la fin de conservation dans le délai de préavis.
     *
     * @return Builder<Application>
     */
    public static function bientotExpires(): Builder
    {
        $mois = (int) config('retention.months', 36);
        $preavis = (int) config('retention.notice_days', 30);

        // Fenêtre : ceux qui basculeront entre aujourd'hui et le préavis, donc
        // pas encore purgeables mais sur le point de l'être.
        $seuilHaut = now()->subMonths($mois)->addDays($preavis);
        $seuilBas = now()->subMonths($mois);

        return Application::query()
            ->whereBetween('updated_at', [$seuilBas, $seuilHaut])
            ->whereDoesntHave('updates', fn (Builder $query) => $query->where('created_at', '>', $seuilBas));
    }

    /**
     * @param  Builder<Application>  $query
     * @param  array{dossiers: int, fichiers: int, supprimes: int, inactifs: int}  $resultat
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
                            : 'Sans activité depuis '.config('retention.months').' mois',
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
