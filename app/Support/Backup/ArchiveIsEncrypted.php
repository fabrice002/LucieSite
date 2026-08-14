<?php

namespace App\Support\Backup;

use League\Flysystem\Local\LocalFilesystemAdapter;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Monitor\HealthCheck;
use ZipArchive;

/**
 * Vérifie que la dernière archive est réellement chiffrée.
 *
 * Sans mot de passe renseigné, spatie/laravel-backup produit une archive
 * parfaitement valide et parfaitement lisible, sans le moindre avertissement.
 * On croit ses scans de passeports protégés sur un stockage distant alors
 * qu'ils sont en clair — et rien ne le signale.
 *
 * Ce contrôle ne s'applique qu'aux archives accessibles localement : sur un
 * disque distant, l'inspection supposerait de rapatrier plusieurs gigaoctets.
 */
class ArchiveIsEncrypted extends HealthCheck
{
    public function name(): string
    {
        return 'Archive chiffrée';
    }

    public function checkHealth(BackupDestination $backupDestination): void
    {
        $this->failIf(
            $backupDestination->backups()->isEmpty(),
            'Aucune sauvegarde trouvée pour cette application.',
        );

        $this->failIf(
            blank(config('backup.backup.password')),
            'Les archives ne sont pas chiffrées : BACKUP_ARCHIVE_PASSWORD est vide. '
            .'Les scans de passeports partent en clair sur le stockage distant.',
        );

        $chemin = $this->cheminLocal($backupDestination);

        // Archive distante : on s'en tient à la vérification de configuration.
        if ($chemin === null) {
            return;
        }

        $this->failUnless(
            $this->estChiffree($chemin),
            'La dernière archive n\'est pas chiffrée, alors qu\'un mot de passe est configuré. '
            .'Vérifiez que PHP dispose de libzip avec le support du chiffrement.',
        );
    }

    private function cheminLocal(BackupDestination $backupDestination): ?string
    {
        $disque = $backupDestination->disk();

        if ($disque->getAdapter()::class !== LocalFilesystemAdapter::class) {
            return null;
        }

        $chemin = $disque->path((string) $backupDestination->backups()->newest()?->path());

        return is_file($chemin) ? $chemin : null;
    }

    private function estChiffree(string $chemin): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($chemin) !== true) {
            return false;
        }

        $entree = $zip->statIndex(0);
        $zip->close();

        return is_array($entree) && $entree['encryption_method'] !== ZipArchive::EM_NONE;
    }
}
