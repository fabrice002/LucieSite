<?php

namespace App\Enums;

/**
 * Où en est un dossier vis-à-vis de sa conservation.
 *
 * Volontairement distinct de ApplicationStatus : celui-ci décrit l'avancement
 * du projet du candidat (« validé », « incomplet »…). Écraser ce statut par une
 * information de conservation ferait perdre, sans retour possible, ce que le
 * cabinet sait du dossier.
 *
 * Un dossier « validé » peut parfaitement arriver à échéance : les deux axes
 * sont indépendants.
 */
enum RetentionState: string
{
    /** Échéance atteinte. Rien n'est supprimé, une décision humaine est attendue. */
    case EnAttenteDeDecision = 'en_attente_de_decision';

    /** Un administrateur a explicitement demandé l'effacement. */
    case MarquePourEffacement = 'marque_pour_effacement';

    public function label(): string
    {
        return match ($this) {
            self::EnAttenteDeDecision => 'En attente de décision',
            self::MarquePourEffacement => 'Marqué pour effacement',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EnAttenteDeDecision => 'warning',
            self::MarquePourEffacement => 'danger',
        };
    }
}
