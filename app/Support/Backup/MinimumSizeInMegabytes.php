<?php

namespace App\Support\Backup;

use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Monitor\HealthCheck;

/**
 * Vérifie qu'une sauvegarde n'est pas anormalement légère.
 *
 * Une archive qui rétrécit brutalement signale un problème silencieux : dump
 * de base tronqué, mysqldump absent, dossier de documents devenu illisible.
 * L'archive existe, elle est récente, et pourtant elle ne contient plus rien
 * d'utile — le pire des cas, puisque rien n'alerte.
 */
class MinimumSizeInMegabytes extends HealthCheck
{
    public function __construct(protected float $megabytes = 1.0) {}

    public function name(): string
    {
        return "Sauvegarde d'au moins {$this->megabytes} Mo";
    }

    public function checkHealth(BackupDestination $backupDestination): void
    {
        $this->failIf(
            $backupDestination->backups()->isEmpty(),
            'Aucune sauvegarde trouvée pour cette application.',
        );

        $recente = $backupDestination->backups()->newest();
        $taille = $recente->sizeInBytes() / 1024 / 1024;

        $this->failIf(
            $taille < $this->megabytes,
            sprintf(
                'La dernière sauvegarde ne pèse que %.2f Mo, en deçà du minimum attendu de %.2f Mo. '
                .'Le dump de la base est peut-être vide : vérifiez que mysqldump est accessible.',
                $taille,
                $this->megabytes,
            ),
        );
    }
}
