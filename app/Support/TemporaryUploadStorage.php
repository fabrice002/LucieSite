<?php

namespace App\Support;

use App\Actions\SubmitApplication;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stockage des téléversements en cours, avant validation du formulaire.
 *
 * FilePond envoie chaque fichier par tranches. Les tranches sont assemblées ici,
 * sur le disque privé, sous un jeton opaque. Rien n'est jamais écrit sous un nom
 * fourni par le client.
 *
 * L'appartenance d'un jeton est conservée dans la session du visiteur plutôt que
 * dans le fichier : les données de session suivent une régénération d'identifiant,
 * ce qui évite qu'un téléversement en cours soit brutalement perdu.
 */
class TemporaryUploadStorage
{
    /** Racine des téléversements en cours, sur le disque privé. */
    public const DIRECTORY = 'tmp-uploads';

    /** Taille maximale d'un fichier, alignée sur la règle max:10240. */
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Nombre de téléversements simultanés tolérés pour une même session. */
    public const MAX_PER_SESSION = 30;

    /** Durée de rétention d'un téléversement abandonné. */
    public const RETENTION_HOURS = 24;

    /** Clé de session listant les jetons appartenant au visiteur. */
    private const SESSION_KEY = 'temporary_uploads';

    /** Nom de repli quand le client n'en a jamais transmis. */
    private const DEFAULT_NAME = 'document';

    /**
     * Open a new transfer and return its opaque token.
     *
     * FilePond n'envoie pas le nom du fichier sur cette requête : il n'arrive
     * qu'avec la première tranche. Le nom reste donc vide jusque-là.
     */
    public function begin(int $length): string
    {
        throw_if($length <= 0 || $length > self::MAX_BYTES, new RuntimeException('Taille annoncée invalide.'));

        $token = Str::uuid()->toString();

        $this->writeMeta($token, [
            'name' => null,
            'length' => $length,
            'created_at' => now()->toIso8601String(),
        ]);

        $this->disk()->put($this->path($token, 'data'), '');

        Session::push(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * Append a chunk at the given offset. Returns the new offset.
     *
     * @param  string|null  $originalName  transmis par FilePond avec chaque tranche
     */
    public function append(string $token, int $offset, string $chunk, ?string $originalName = null): int
    {
        $meta = $this->meta($token);
        $length = strlen($chunk);

        // Le nom du fichier voyage avec les tranches : on le retient dès qu'il
        // se présente, sinon le document finirait stocké sans extension.
        if ($originalName !== null && $meta['name'] === null) {
            $meta['name'] = $this->sanitizeName($originalName);
            $this->writeMeta($token, $meta);
        }

        throw_if($offset < 0, new RuntimeException('Décalage invalide.'));
        throw_if($offset + $length > $meta['length'], new RuntimeException('Le fichier dépasse la taille annoncée.'));
        throw_if($offset + $length > self::MAX_BYTES, new RuntimeException('Fichier trop volumineux.'));

        $handle = fopen($this->disk()->path($this->path($token, 'data')), 'cb');
        throw_unless(is_resource($handle), new RuntimeException('Impossible d\'ouvrir le fichier temporaire.'));

        try {
            throw_unless(flock($handle, LOCK_EX), new RuntimeException('Fichier temporaire verrouillé.'));
            fseek($handle, $offset);
            fwrite($handle, $chunk);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $this->offset($token);
    }

    /**
     * Get how many bytes have already been received.
     */
    public function offset(string $token): int
    {
        $this->meta($token);

        return (int) $this->disk()->size($this->path($token, 'data'));
    }

    /**
     * Turn a completed transfer into an UploadedFile.
     *
     * Le fichier repart ensuite dans le circuit de validation habituel : la
     * règle mimes inspecte son contenu réel, exactement comme pour un envoi
     * classique sans JavaScript.
     */
    public function toUploadedFile(string $token): ?UploadedFile
    {
        try {
            $meta = $this->meta($token);
        } catch (RuntimeException) {
            return null;
        }

        $absolute = $this->disk()->path($this->path($token, 'data'));

        if (! is_file($absolute) || filesize($absolute) !== $meta['length']) {
            return null;
        }

        return new UploadedFile($absolute, $meta['name'] ?? self::DEFAULT_NAME, null, null, test: true);
    }

    /**
     * Drop a transfer and everything it holds.
     */
    public function forget(string $token): void
    {
        if (! $this->isValidToken($token)) {
            return;
        }

        $this->disk()->deleteDirectory(self::DIRECTORY.'/'.$token);

        Session::put(self::SESSION_KEY, array_values(array_diff($this->owned(), [$token])));
    }

    /**
     * Determine whether the current visitor opened this transfer.
     */
    public function owns(string $token): bool
    {
        return $this->isValidToken($token) && in_array($token, $this->owned(), true);
    }

    /**
     * Count the transfers currently open for the visitor.
     */
    public function countForSession(): int
    {
        return count($this->owned());
    }

    /**
     * Determine whether a token looks like one we issued.
     */
    public function isValidToken(string $token): bool
    {
        return Str::isUuid($token);
    }

    /**
     * Delete every transfer consumed or left behind past the retention window.
     *
     * @return int the number of transfers removed
     */
    public function purgeStale(?Carbon $before = null): int
    {
        $before ??= now()->subHours(self::RETENTION_HOURS);
        $removed = 0;

        foreach ($this->disk()->directories(self::DIRECTORY) as $directory) {
            // Le fichier « data » a disparu : le transfert a été consommé par une
            // soumission, il ne reste que des miettes.
            if (! $this->disk()->exists($directory.'/data')) {
                $this->disk()->deleteDirectory($directory);
                $removed++;

                continue;
            }

            $metaPath = $directory.'/meta.json';

            $createdAt = $this->disk()->exists($metaPath)
                ? $this->decode($this->disk()->get($metaPath))['created_at'] ?? null
                : null;

            if (! is_string($createdAt) || Carbon::parse($createdAt)->lessThan($before)) {
                $this->disk()->deleteDirectory($directory);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return array{name: string|null, length: int, created_at: string}
     */
    private function meta(string $token): array
    {
        throw_unless($this->owns($token), new RuntimeException('Téléversement introuvable.'));
        throw_unless(
            $this->disk()->exists($this->path($token, 'meta.json')),
            new RuntimeException('Téléversement introuvable.'),
        );

        /** @var array{name: string|null, length: int, created_at: string} $meta */
        $meta = $this->decode($this->disk()->get($this->path($token, 'meta.json')));

        return $meta;
    }

    /**
     * @param  array{name: string|null, length: int, created_at: string}  $meta
     */
    private function writeMeta(string $token, array $meta): void
    {
        $this->disk()->put(
            $this->path($token, 'meta.json'),
            (string) json_encode($meta, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Keep only the file name, never a path.
     *
     * Ce nom vient du client : il ne sert qu'à l'affichage et au nom du
     * téléchargement, jamais à écrire sur le disque.
     */
    private function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim($name);

        return $name !== '' && $name !== '.' && $name !== '..'
            ? Str::limit($name, 180, '')
            : self::DEFAULT_NAME;
    }

    /**
     * @return list<string>
     */
    private function owned(): array
    {
        /** @var list<string> $tokens */
        $tokens = array_values(array_filter(
            (array) Session::get(self::SESSION_KEY, []),
            fn (mixed $token): bool => is_string($token),
        ));

        return $tokens;
    }

    private function path(string $token, string $file): string
    {
        return self::DIRECTORY.'/'.$token.'/'.$file;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(?string $json): array
    {
        if (! is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function disk(): Filesystem
    {
        return Storage::disk(SubmitApplication::DISK);
    }
}
