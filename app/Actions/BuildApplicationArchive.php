<?php

namespace App\Actions;

use App\Enums\DocumentScanStatus;
use App\Models\Application;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Assemble les documents d'un dossier dans une archive ZIP téléchargeable.
 *
 * Un dossier peut contenir huit scans de 10 Mo. Les fichiers sont donc ajoutés
 * par référence (addFile) et non par leur contenu : ZipArchive lit chaque
 * fichier depuis le disque au moment de la compression, sans jamais charger
 * l'ensemble en mémoire.
 *
 * L'archive est écrite dans un fichier temporaire hors du disque public et
 * supprimée dès que la réponse a été envoyée.
 */
class BuildApplicationArchive
{
    public function __invoke(Application $application): BinaryFileResponse
    {
        // Un fichier infecté ne part pas, même noyé dans une archive.
        $documents = $application->documents()
            ->where('scan_status', '!=', DocumentScanStatus::Infecte)
            ->get();

        throw_if($documents->isEmpty(), new RuntimeException('Ce dossier ne contient aucun document téléchargeable.'));

        // Compresser huit scans prend du temps ; la requête ne doit pas être
        // interrompue en cours de route par max_execution_time.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

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

            // addFile, jamais addFromString : le contenu reste sur le disque
            // et n'est lu qu'au moment de compresser.
            $zip->addFile($disk->path($document->path), $nom);
        }

        throw_unless($zip->close(), new RuntimeException('Impossible de finaliser l\'archive.'));

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
