<?php

namespace App\Actions;

use App\Enums\DocumentScanStatus;
use App\Models\Application;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

/**
 * Assemble les documents d'un dossier dans une archive ZIP téléchargeable.
 *
 * L'archive est **diffusée en flux** : rien n'est construit en mémoire, rien
 * n'est écrit dans un fichier temporaire. Chaque pièce est lue depuis le disque
 * et poussée vers le navigateur au fil de l'eau.
 *
 * Un dossier peut contenir huit scans de 10 Mo : une construction en mémoire
 * saturerait memory_limit, et un fichier temporaire ferait attendre le premier
 * octet jusqu'à la fin de la compression.
 */
class BuildApplicationArchive
{
    public function __invoke(Application $application): StreamedResponse
    {
        // Un fichier infecté ne part pas, même noyé dans une archive.
        $documents = $application->documents()
            ->where('scan_status', '!=', DocumentScanStatus::Infecte)
            ->get();

        throw_if($documents->isEmpty(), new RuntimeException('Ce dossier ne contient aucun document téléchargeable.'));

        $disque = Storage::disk(SubmitApplication::DISK);
        $nomArchive = $application->reference.'.zip';

        return response()->stream(function () use ($documents, $disque, $nomArchive): void {
            $zip = new ZipStream(
                outputName: $nomArchive,
                // Les en-têtes sont posés par la réponse Laravel elle-même.
                sendHttpHeaders: false,
                defaultEnableZeroHeader: true,
            );

            $compteurs = [];

            foreach ($documents as $document) {
                if (! $disque->exists($document->path)) {
                    continue;
                }

                // Nom lisible dans l'archive : « cv-1 - mon-cv.pdf ». Le nom
                // d'origine vient du candidat, on l'assainit avant usage.
                $type = $document->type->value;
                $compteurs[$type] = ($compteurs[$type] ?? 0) + 1;

                $nom = sprintf(
                    '%s-%d - %s',
                    $type,
                    $compteurs[$type],
                    $this->assainir($document->downloadName()),
                );

                // addFileFromStream : le fichier transite par un tampon, jamais
                // en entier par la mémoire du processus.
                $flux = $disque->readStream($document->path);

                if (! is_resource($flux)) {
                    continue;
                }

                $zip->addFileFromStream($nom, $flux);
                fclose($flux);
            }

            $zip->finish();
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$nomArchive.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            // Sans cela, un frontal pourrait mettre la réponse en tampon et
            // annuler tout l'intérêt du flux.
            'X-Accel-Buffering' => 'no',
        ]);
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
