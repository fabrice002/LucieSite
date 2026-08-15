<?php

namespace App\Observers;

use App\Models\Application;

/**
 * Tient à jour l'échéance de conservation d'un dossier.
 *
 * Toute activité réelle — dépôt, changement de statut, note interne, message
 * au candidat — repousse l'échéance d'autant. Un dossier réellement suivi
 * n'arrive donc jamais à échéance.
 *
 * Les écritures qui ne concernent que la conservation elle-même sont exclues :
 * sans cela, envoyer un rappel ou marquer un dossier repousserait l'échéance
 * que l'on cherche justement à faire respecter.
 */
class ApplicationRetentionObserver
{
    public function saving(Application $application): void
    {
        if (! $this->activiteReelle($application)) {
            return;
        }

        $application->repousserEcheance();
    }

    /**
     * Le dossier a-t-il changé autrement que sur ses champs de conservation ?
     */
    private function activiteReelle(Application $application): bool
    {
        if (! $application->exists) {
            return true;
        }

        $modifies = array_keys($application->getDirty());

        return array_diff($modifies, Application::COLONNES_DE_CONSERVATION) !== [];
    }
}
