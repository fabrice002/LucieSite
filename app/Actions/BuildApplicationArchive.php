<?php

namespace App\Actions;

use App\Models\Application;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Assemble les documents d'un dossier dans une archive ZIP téléchargeable.
 *
 * L'archive est écrite dans un fichier temporaire hors du disque public et
 * supprimée dès que la réponse a été envoyée.
 */
class BuildApplicationArchive
{
    public function __invoke(Application $application): BinaryFileResponse
    {
        $documents = $application->documents()->get();

        throw_if($documents->isEmpty(), new RuntimeException('Ce dossier ne contient aucun document.'));

        $archivePath = tempnam(sys_get_temp_dir(), 'ln-archive-');
        throw_unless(is_string($archivePath), new RuntimeException('Impossible de créer l\'archive.'));

        $zip = new ZipArchive;
        throw_unless(
            $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true,
            new RuntimeException('Impossible d\'ouvrir l\'archive.'),
        );

        $disk = Storage::disk(SubmitApplication::DISK);
        $compteurs = [];

        foreach ($documents as $document) {
            if (! $disk->exists($document->path)) {
                continue;
            }

            // Nom lisible dans l'archive : « cv-1 - mon-cv.pdf ». Le nom d'origine
            // n'a servi qu'à l'affichage, on l'assainit avant de l'utiliser ici.
            $type = $document->type->value;
            $compteurs[$type] = ($compteurs[$type] ?? 0) + 1;

            $nom = sprintf(
                '%s-%d - %s',
                $type,
                $compteurs[$type],
                $this->assainir($document->downloadName()),
            );

            $zip->addFromString($nom, (string) $disk->get($document->path));
        }

        $zip->close();

        return response()
            ->download($archivePath, $application->reference.'.zip', [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Strip anything that could escape the archive or break a file system.
     */
    private function assainir(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $name) ?? 'document';

        return trim($name) !== '' ? $name : 'document';
    }
}
