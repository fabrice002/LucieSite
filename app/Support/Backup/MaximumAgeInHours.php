<?php

namespace App\Support\Backup;

use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Monitor\HealthCheck;

/**
 * Vérifie qu'une sauvegarde récente existe, à l'heure près.
 *
 * Le contrôle fourni par spatie raisonne en jours entiers, trop grossier ici :
 * la sauvegarde tourne à 01h30 et le contrôle à 08h00. Avec un seuil d'un jour,
 * une nuit manquée passerait inaperçue pendant près de vingt-quatre heures.
 *
 * Vingt-cinq heures laissent juste assez de marge pour un décalage d'exécution,
 * sans laisser passer une nuit blanche.
 */
class MaximumAgeInHours extends HealthCheck
{
    public function __construct(protected int $hours = 25) {}

    public function name(): string
    {
        return "Sauvegarde datant de moins de {$this->hours} heures";
    }

    public function checkHealth(BackupDestination $backupDestination): void
    {
        // Une sauvegarde qui ne se lance plus n'échoue pas : elle n'existe pas.
        // C'est précisément le cas que ce contrôle doit rattraper.
        $this->failIf(
            $backupDestination->backups()->isEmpty(),
            'Aucune sauvegarde trouvée pour cette application.',
        );

        $recente = $backupDestination->backups()->newest();

        $this->failIf(
            $recente->date()->lessThanOrEqualTo(now()->subHours($this->hours)),
            sprintf(
                'La dernière sauvegarde date du %s, soit plus de %d heures. Vérifiez le planificateur (cron) et l\'espace disque.',
                $recente->date()->translatedFormat('j F Y à H:i'),
                $this->hours,
            ),
        );
    }
}
