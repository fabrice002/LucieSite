<?php

namespace App\Http\Controllers;

use App\Support\TemporaryUploadStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Point d'entrée des téléversements par tranches de FilePond.
 *
 * Le protocole est celui de FilePond :
 *   POST   ouvre un transfert et renvoie un jeton en texte brut
 *   PATCH  écrit une tranche à l'emplacement indiqué par Upload-Offset
 *   HEAD   renvoie l'avancement, ce qui permet de reprendre après coupure
 *   DELETE abandonne le transfert
 */
class TemporaryUploadController extends Controller
{
    public function __construct(private readonly TemporaryUploadStorage $storage) {}

    /**
     * Open a transfer and hand back its token.
     */
    public function store(Request $request): Response
    {
        $length = (int) $request->header('Upload-Length', '0');
        $name = (string) ($request->header('Upload-Name') ?? 'document');

        if ($length <= 0 || $length > TemporaryUploadStorage::MAX_BYTES) {
            return $this->plain('Fichier trop volumineux.', 413);
        }

        if ($this->storage->countForSession() >= TemporaryUploadStorage::MAX_PER_SESSION) {
            return $this->plain('Trop de fichiers en cours d\'envoi.', 429);
        }

        try {
            $token = $this->storage->begin($name, $length);
        } catch (RuntimeException $exception) {
            return $this->plain($exception->getMessage(), 422);
        }

        return $this->plain($token);
    }

    /**
     * Write one chunk.
     */
    public function patch(Request $request): Response
    {
        $token = (string) $request->query('patch', '');
        $offset = (int) $request->header('Upload-Offset', '0');

        try {
            $newOffset = $this->storage->append($token, $offset, $request->getContent());
        } catch (RuntimeException $exception) {
            return $this->plain($exception->getMessage(), 422);
        }

        return $this->plain('', 204)->header('Upload-Offset', (string) $newOffset);
    }

    /**
     * Report progress so an interrupted upload can resume where it stopped.
     */
    public function head(Request $request): Response
    {
        $token = (string) $request->query('patch', '');

        try {
            $offset = $this->storage->offset($token);
        } catch (RuntimeException) {
            return $this->plain('', 404);
        }

        return $this->plain('', 200)->header('Upload-Offset', (string) $offset);
    }

    /**
     * Abandon a transfer.
     */
    public function destroy(Request $request): Response
    {
        $token = trim($request->getContent());

        if ($this->storage->isValidToken($token)) {
            $this->storage->forget($token);
        }

        return $this->plain('', 204);
    }

    private function plain(string $body, int $status = 200): Response
    {
        return response($body, $status)->header('Content-Type', 'text/plain');
    }
}
