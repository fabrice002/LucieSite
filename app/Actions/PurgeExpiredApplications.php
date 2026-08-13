<?php

namespace App\Actions;

use App\Models\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Efface définitivement les dossiers supprimés depuis plus de 90 jours.
 *
 * La suppression douce laisse les scans de passeports sur le disque. Passé le
 * délai de rétention, ils doivent disparaître pour de bon — fichiers compris.
 */
class PurgeExpiredApplications
{
    /** Durée de rétention après suppression, en jours. */
    public const RETENTION_DAYS = 90;

    /**
     * @return array{dossiers: int, fichiers: int}
     */
    public function __invoke(?Carbon $before = null): array
    {
        $before ??= now()->subDays(self::RETENTION_DAYS);

        $disk = Storage::disk(SubmitApplication::DISK);
        $dossiers = 0;
        $fichiers = 0;

        Application::onlyTrashed()
            ->where('deleted_at', '<=', $before)
            ->with('documents')
            ->chunkById(100, function ($expires) use ($disk, &$dossiers, &$fichiers): void {
                foreach ($expires as $application) {
                    foreach ($application->documents as $document) {
                        if ($disk->exists($document->path)) {
                            $disk->delete($document->path);
                            $fichiers++;
                        }
                    }

                    // Le dossier du candidat sur le disque, désormais vide.
                    $disk->deleteDirectory('documents/'.$application->reference);

                    // forceDelete supprime les lignes documents en cascade.
                    $application->forceDelete();
                    $dossiers++;
                }
            });

        return ['dossiers' => $dossiers, 'fichiers' => $fichiers];
    }
}
