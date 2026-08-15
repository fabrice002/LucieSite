<?php

namespace App\Observers;

use App\Models\ApplicationUpdate;

/**
 * Un message adressé au candidat compte comme une activité sur son dossier, et
 * repousse donc son échéance de conservation.
 *
 * Le report est appliqué explicitement, et non déduit d'un changement de
 * updated_at. Deux raisons :
 *
 *   - la propriété $touches d'Eloquent passe par une mise à jour en masse, qui
 *     ne déclenche aucun événement de modèle ;
 *   - touch() sur l'instance ne rend rien « dirty » lorsqu'il tombe dans la
 *     même seconde que la dernière écriture, et l'observateur du dossier ne
 *     verrait alors aucune activité.
 *
 * Dans les deux cas le dossier resterait en file d'attente de suppression alors
 * qu'il est activement suivi.
 */
class ApplicationUpdateObserver
{
    public function created(ApplicationUpdate $applicationUpdate): void
    {
        $application = $applicationUpdate->application;

        $application->repousserEcheance();

        // touch() enregistre : même si updated_at tombe dans la même seconde et
        // ne change donc pas, les colonnes de conservation sont modifiées et
        // seront écrites.
        $application->touch();
    }
}
