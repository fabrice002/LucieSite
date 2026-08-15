<?php

namespace App\Actions;

use App\Enums\RetentionState;
use App\Models\Application;

/**
 * Les deux seules issues possibles pour un dossier arrivé à échéance.
 *
 * Toute décision est journalisée avec son auteur : détruire des pièces
 * d'identité doit rester traçable, et pouvoir être rattaché à quelqu'un.
 */
class DecideApplicationRetention
{
    /**
     * Accorde un sursis. Le dossier quitte la file d'attente.
     */
    public function conserver(Application $application, int $mois = PurgeExpiredApplications::SURSIS_MOIS): void
    {
        // Sans désactiver les timestamps, l'observateur verrait une activité et
        // repousserait l'échéance de la durée de conservation entière (36 mois)
        // au lieu du sursis décidé ici.
        $application->timestamps = false;
        $application->retention_state = null;
        $application->retention_due_at = now()->addMonths($mois);
        $application->retention_reminded_at = null;
        $application->save();

        activity('dossier')
            ->performedOn($application)
            ->withProperties([
                'reference' => $application->reference,
                'nouvelle_echeance' => $application->retention_due_at->toDateString(),
            ])
            ->log('Conservation prolongée de '.$mois.' mois');
    }

    /**
     * Marque le dossier pour effacement, puis l'efface.
     *
     * Le marquage n'est pas qu'une étape intermédiaire : il subsiste si
     * l'effacement échoue, et la commande planifiée le reprendra. Aucun dossier
     * ne peut donc rester dans un entre-deux silencieux.
     */
    public function effacer(Application $application, PurgeExpiredApplications $purge): void
    {
        $application->timestamps = false;
        $application->retention_state = RetentionState::MarquePourEffacement;
        $application->save();

        activity('dossier')
            ->performedOn($application)
            ->withProperties(['reference' => $application->reference])
            ->log('Effacement définitif demandé');

        $purge->effacerCeDossier($application);
    }
}
