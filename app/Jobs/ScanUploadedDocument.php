<?php

namespace App\Jobs;

use App\Actions\SubmitApplication;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentScanStatus;
use App\Models\Document;
use App\Models\User;
use App\Notifications\InfectedDocumentFound;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Soumet une pièce déposée à ClamAV.
 *
 * Le job est volontairement dégradable : si l'antivirus est absent, arrêté ou
 * trop lent, le document est marqué « indisponible » et rien n'est bloqué. Un
 * antivirus en panne ne doit jamais empêcher le cabinet de travailler, ni un
 * candidat de déposer son dossier.
 */
class ScanUploadedDocument implements ShouldQueue
{
    use Queueable;

    /** Un fichier vérolé le reste : inutile de s'acharner. */
    public int $tries = 1;

    public function __construct(public readonly Document $document) {}

    public function handle(): void
    {
        if (! config('documents.scan.enabled')) {
            $this->marquer(DocumentScanStatus::Indisponible);

            return;
        }

        $disque = Storage::disk(SubmitApplication::DISK);

        if (! $disque->exists($this->document->path)) {
            // Le dossier a pu être purgé entre le dépôt et l'analyse.
            return;
        }

        $this->marquer($this->analyser($disque->path($this->document->path)));
    }

    /**
     * Interroge ClamAV et traduit son verdict.
     *
     * clamdscan renvoie 0 pour un fichier sain, 1 pour un fichier infecté, et
     * 2 ou davantage pour une erreur d'exécution.
     */
    private function analyser(string $chemin): DocumentScanStatus
    {
        $commande = (string) config('documents.scan.command');

        // Un binaire introuvable fait sortir le shell avec le code 1 — celui-là
        // même que ClamAV réserve aux fichiers infectés. Sans cette vérification,
        // une installation sans antivirus condamnerait tous les dossiers.
        if ((new ExecutableFinder)->find($commande) === null) {
            Log::warning('Binaire antivirus introuvable.', [
                'commande' => $commande,
                'document' => $this->document->getKey(),
            ]);

            return DocumentScanStatus::Indisponible;
        }

        $process = new Process(
            [$commande, '--no-summary', '--fdpass', $chemin],
            timeout: (float) config('documents.scan.timeout', 60),
        );

        try {
            $process->run();
        } catch (ProcessException $exception) {
            Log::warning('Analyse antivirus impossible.', [
                'document' => $this->document->getKey(),
                'raison' => $exception->getMessage(),
            ]);

            return DocumentScanStatus::Indisponible;
        }

        return match ($process->getExitCode()) {
            0 => DocumentScanStatus::Sain,
            // Deuxième garde-fou : on n'accuse un fichier que si ClamAV a
            // effectivement nommé une signature.
            1 => str_contains($process->getOutput(), 'FOUND')
                ? DocumentScanStatus::Infecte
                : $this->indisponible($process),
            default => $this->indisponible($process),
        };
    }

    private function indisponible(Process $process): DocumentScanStatus
    {
        Log::warning('ClamAV n\'a pas pu analyser le fichier.', [
            'document' => $this->document->getKey(),
            'code' => $process->getExitCode(),
            'sortie' => trim($process->getErrorOutput() ?: $process->getOutput()),
        ]);

        return DocumentScanStatus::Indisponible;
    }

    private function marquer(DocumentScanStatus $statut): void
    {
        $this->document->forceFill([
            'scan_status' => $statut,
            'scanned_at' => now(),
        ])->save();

        if ($statut === DocumentScanStatus::Infecte) {
            $this->alerter();
        }
    }

    /**
     * Un fichier infecté met le dossier en attente et prévient le cabinet.
     */
    private function alerter(): void
    {
        $application = $this->document->application;

        if ($application->status !== ApplicationStatus::Incomplet) {
            $application->update(['status' => ApplicationStatus::Incomplet]);
        }

        activity('document')
            ->performedOn($this->document)
            ->withProperties([
                'application' => $application->reference,
                'original_name' => $this->document->original_name,
            ])
            ->log('Document détecté comme infecté');

        $administrateurs = User::role('admin')->get();

        if ($administrateurs->isNotEmpty()) {
            Notification::send($administrateurs, new InfectedDocumentFound($this->document));
        }
    }
}
