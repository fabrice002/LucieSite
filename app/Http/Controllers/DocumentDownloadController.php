<?php

namespace App\Http\Controllers;

use App\Actions\SubmitApplication;
use App\Models\Document;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    /**
     * En-têtes qui empêchent le navigateur d'exécuter quoi que ce soit.
     *
     * La règle « mimes » rejette un exécutable renommé, mais pas un PDF
     * légitimement formé porteur de JavaScript, ni une image SVG contenant un
     * script. Ces fichiers sont ouverts chaque jour par le cabinet : ils
     * doivent être téléchargés, jamais rendus dans l'onglet.
     *
     * @var array<string, string>
     */
    private const HEADERS = [
        // Le navigateur ne devine pas le type : il prend celui qu'on annonce.
        'X-Content-Type-Options' => 'nosniff',
        // Aucune ressource ne peut être chargée si le fichier était rendu.
        'Content-Security-Policy' => "default-src 'none'; sandbox",
        // L'URL du document ne fuit pas vers un site tiers.
        'Referrer-Policy' => 'no-referrer',
        // Jamais en cache partagé : ces fichiers sont nominatifs.
        'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        'X-Frame-Options' => 'DENY',
    ];

    /**
     * Stream a candidate document from the private disk.
     *
     * La route est authentifiée et protégée par DocumentPolicy : le fichier
     * n'est jamais accessible par une URL directe.
     */
    public function __invoke(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        // Un fichier reconnu infecté ne sort pas du serveur.
        abort_unless($document->isDownloadable(), 403, 'Ce fichier a été détecté comme infecté.');

        $disk = Storage::disk(SubmitApplication::DISK);

        abort_unless($disk->exists($document->path), 404);

        // Qui a consulté quelle pièce, et quand : le site héberge des scans de
        // passeports, cette trace n'est pas optionnelle.
        activity('document')
            ->performedOn($document)
            ->causedBy(auth()->user())
            ->withProperties([
                'application' => $document->application->reference,
                'type' => $document->type->value,
                'original_name' => $document->original_name,
            ])
            ->log('Document téléchargé');

        // download() pose déjà « Content-Disposition: attachment ».
        return $disk->download(
            $document->path,
            $document->downloadName(),
            [
                ...self::HEADERS,
                // Type générique : le navigateur n'a aucune raison de tenter
                // un rendu, même si l'en-tête de disposition était ignoré.
                'Content-Type' => 'application/octet-stream',
            ],
        );
    }
}
