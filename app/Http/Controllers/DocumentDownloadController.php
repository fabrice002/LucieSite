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
     * Stream a candidate document from the private disk.
     *
     * La route est authentifiée et protégée par DocumentPolicy : le fichier
     * n'est jamais accessible par une URL directe.
     */
    public function __invoke(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        $disk = Storage::disk(SubmitApplication::DISK);

        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, $document->original_name);
    }
}
