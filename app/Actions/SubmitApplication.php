<?php

namespace App\Actions;

use App\Enums\DocumentType;
use App\Models\Application;
use App\Notifications\ApplicationReceived;
use App\Notifications\ApplicationSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Enregistre un dossier déposé par un candidat.
 *
 * Les fichiers vont exclusivement sur le disque privé, sous un nom généré en
 * UUID. Le nom d'origine n'est conservé que pour l'affichage et ne sert jamais
 * à écrire sur le disque.
 */
class SubmitApplication
{
    /**
     * Le disque privé. Sa racine est storage/app/private (config/filesystems).
     */
    public const DISK = 'local';

    public function __construct(private readonly GenerateApplicationReference $generateReference) {}

    /**
     * @param  array<string, string|null>  $attributes
     * @param  array<string, list<UploadedFile>>  $documents  indexé par valeur de DocumentType
     */
    public function __invoke(array $attributes, array $documents, ?string $ipAddress = null): Application
    {
        /** @var list<string> $storedPaths */
        $storedPaths = [];

        try {
            $application = DB::transaction(function () use ($attributes, $documents, $ipAddress, &$storedPaths): Application {
                $application = Application::create([
                    ...$attributes,
                    'reference' => ($this->generateReference)(),
                    'ip_address' => $ipAddress,
                ]);

                foreach ($documents as $type => $files) {
                    foreach ($files as $file) {
                        $storedPaths[] = $this->storeDocument($application, DocumentType::from($type), $file);
                    }
                }

                return $application;
            }, attempts: 3);
        } catch (Throwable $exception) {
            // La transaction a été annulée : les fichiers déjà écrits sur le
            // disque n'ont plus de ligne correspondante, on les supprime.
            Storage::disk(self::DISK)->delete($storedPaths);

            throw $exception;
        }

        $this->notify($application);

        return $application;
    }

    /**
     * Write a single upload to the private disk and record it.
     *
     * @return string the stored path
     */
    private function storeDocument(Application $application, DocumentType $type, UploadedFile $file): string
    {
        // extension() déduit l'extension du contenu réel du fichier, jamais du
        // nom fourni par le client.
        $extension = $file->extension() ?: 'bin';

        $path = $file->storeAs(
            'documents/'.$application->reference,
            Str::uuid()->toString().'.'.$extension,
            ['disk' => self::DISK],
        );

        throw_unless(is_string($path), new \RuntimeException('Échec de l\'enregistrement du document.'));

        $application->documents()->create([
            'type' => $type,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return $path;
    }

    /**
     * Notify the candidate and the office. Both notifications are queued so the
     * candidate never waits for SMTP.
     */
    private function notify(Application $application): void
    {
        Notification::route('mail', $application->email)
            ->notify(new ApplicationSubmitted($application));

        $adminAddress = config('mail.admin_address');

        if (is_string($adminAddress) && $adminAddress !== '') {
            Notification::route('mail', $adminAddress)
                ->notify(new ApplicationReceived($application));
        }
    }
}
