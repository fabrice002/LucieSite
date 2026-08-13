<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Carbon\CarbonInterface;
use Spatie\Activitylog\Models\Activity;

/**
 * Reconstitue l'historique lisible d'un dossier.
 *
 * Le journal brut de spatie/activitylog stocke des différences d'attributs.
 * Cette classe les traduit en phrases : qui a fait quoi, quand.
 */
class ApplicationHistory
{
    /**
     * @return list<array{date: CarbonInterface, auteur: string, action: string, detail: string|null}>
     */
    public function __invoke(Application $application): array
    {
        $entrees = [];

        $journal = Activity::query()
            ->where('subject_type', $application->getMorphClass())
            ->where('subject_id', $application->getKey())
            ->with('causer')
            ->latest('id')
            ->get();

        foreach ($journal as $activite) {
            $entrees[] = [
                'date' => $activite->created_at ?? now(),
                'auteur' => $this->auteur($activite),
                'action' => $this->action($activite),
                'detail' => $this->detail($activite),
            ];
        }

        // Le téléchargement des pièces se journalise sur les documents.
        $telechargements = Activity::query()
            ->where('log_name', 'document')
            ->whereJsonContains('properties->application', $application->reference)
            ->with('causer')
            ->latest('id')
            ->get();

        foreach ($telechargements as $activite) {
            $entrees[] = [
                'date' => $activite->created_at ?? now(),
                'auteur' => $this->auteur($activite),
                'action' => 'Document téléchargé',
                'detail' => is_string($activite->properties['original_name'] ?? null)
                    ? $activite->properties['original_name']
                    : null,
            ];
        }

        usort($entrees, fn (array $a, array $b): int => $b['date'] <=> $a['date']);

        return $entrees;
    }

    private function auteur(Activity $activite): string
    {
        $causer = $activite->causer;

        if ($causer === null) {
            // Un dépôt vient du formulaire public : personne n'est authentifié.
            return 'Le candidat, depuis le site';
        }

        /** @var User $causer */
        return $causer->name;
    }

    private function action(Activity $activite): string
    {
        // Création, suppression, restauration : la description du journal suffit.
        // Sans ce garde-fou, le dépôt initial serait étiqueté « Statut modifié »,
        // puisque le statut y passe bien de rien à « nouveau ».
        if ($activite->event !== 'updated') {
            return (string) $activite->description;
        }

        $ancien = $activite->properties['old']['status'] ?? null;
        $nouveau = $activite->properties['attributes']['status'] ?? null;

        if (is_string($nouveau) && $ancien !== $nouveau) {
            return 'Statut modifié';
        }

        if (array_key_exists('internal_notes', (array) ($activite->properties['attributes'] ?? []))) {
            return 'Notes internes modifiées';
        }

        return (string) $activite->description;
    }

    private function detail(Activity $activite): ?string
    {
        if ($activite->event !== 'updated') {
            return null;
        }

        $ancien = $activite->properties['old']['status'] ?? null;
        $nouveau = $activite->properties['attributes']['status'] ?? null;

        if (! is_string($nouveau) || $ancien === $nouveau) {
            return null;
        }

        $vers = ApplicationStatus::tryFrom($nouveau)?->label() ?? $nouveau;

        return is_string($ancien)
            ? (ApplicationStatus::tryFrom($ancien)?->label() ?? $ancien).' → '.$vers
            : $vers;
    }
}
