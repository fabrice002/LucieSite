<?php

namespace App\Console\Commands;

use App\Actions\PurgeExpiredApplications;
use App\Models\Application;
use Illuminate\Console\Command;

class PurgeApplications extends Command
{
    protected $signature = 'ln:purge-applications {--dry-run : Affiche ce qui serait supprimé sans rien effacer}';

    protected $description = 'Efface définitivement les dossiers supprimés depuis plus de 90 jours, fichiers compris';

    public function handle(PurgeExpiredApplications $purge): int
    {
        $limite = now()->subDays(PurgeExpiredApplications::RETENTION_DAYS);

        if ($this->option('dry-run')) {
            $nombre = Application::onlyTrashed()
                ->where('deleted_at', '<=', $limite)
                ->count();

            $this->info("{$nombre} dossier(s) seraient supprimés définitivement.");

            return self::SUCCESS;
        }

        ['dossiers' => $dossiers, 'fichiers' => $fichiers] = $purge();

        $this->info($dossiers === 0
            ? 'Aucun dossier à purger.'
            : "{$dossiers} dossier(s) et {$fichiers} fichier(s) supprimés définitivement.");

        return self::SUCCESS;
    }
}
