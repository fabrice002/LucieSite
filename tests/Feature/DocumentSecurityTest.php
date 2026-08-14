<?php

use App\Actions\BuildApplicationArchive;
use App\Actions\SubmitApplication;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentScanStatus;
use App\Jobs\ScanUploadedDocument;
use App\Models\Application;
use App\Models\Document;
use App\Models\User;
use App\Notifications\InfectedDocumentFound;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

function membreDuCabinet(string $role = 'agent'): User
{
    Role::findOrCreate($role, 'web');

    return tap(User::factory()->create())->assignRole($role);
}

function documentSur(Application $application, DocumentScanStatus $scan = DocumentScanStatus::Sain): Document
{
    $document = Document::factory()->create([
        'application_id' => $application->id,
        'original_name' => 'passeport.pdf',
        'path' => 'documents/'.$application->reference.'/'.Str::uuid()->toString().'.pdf',
        'scan_status' => $scan,
    ]);

    Storage::disk(SubmitApplication::DISK)->put($document->path, 'contenu du scan');

    return $document;
}

beforeEach(function () {
    Storage::fake(SubmitApplication::DISK);
});

/*
|--------------------------------------------------------------------------
| B.1 — Le téléchargement ne s'ouvre jamais dans le navigateur
|--------------------------------------------------------------------------
*/

it('force le téléchargement et interdit tout rendu par le navigateur', function () {
    $document = documentSur(Application::factory()->create());

    $response = $this->actingAs(membreDuCabinet())
        ->get(route('documents.download', $document));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Frame-Options', 'DENY')
        // Type générique : même si la disposition était ignorée, rien à rendre.
        ->assertHeader('Content-Type', 'application/octet-stream');

    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")
        ->and($response->headers->get('Content-Disposition'))->toStartWith('attachment')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('ne propose aucune prévisualisation en ligne dans le back-office', function () {
    $application = Application::factory()->create();
    documentSur($application);

    $response = $this->actingAs(membreDuCabinet())
        ->get('/admin/applications/'.$application->reference);

    $response->assertOk();

    // Ni ouverture dans un onglet, ni visionneuse, ni miniature.
    expect($response->getContent())
        ->not->toContain('target="_blank"')
        ->not->toContain('<iframe')
        ->not->toContain('<embed');
});

/*
|--------------------------------------------------------------------------
| B.2 — Analyse antivirus
|--------------------------------------------------------------------------
*/

it('marque l\'analyse indisponible quand ClamAV est désactivé', function () {
    config(['documents.scan.enabled' => false]);

    $document = documentSur(Application::factory()->create(), DocumentScanStatus::EnAttente);

    (new ScanUploadedDocument($document))->handle();

    expect($document->refresh()->scan_status)->toBe(DocumentScanStatus::Indisponible)
        ->and($document->scanned_at)->not->toBeNull()
        // Un antivirus absent ne bloque rien.
        ->and($document->isDownloadable())->toBeTrue();
});

it('marque l\'analyse indisponible quand le binaire est introuvable', function () {
    config([
        'documents.scan.enabled' => true,
        'documents.scan.command' => 'binaire-clamav-inexistant',
    ]);

    $document = documentSur(Application::factory()->create(), DocumentScanStatus::EnAttente);

    (new ScanUploadedDocument($document))->handle();

    expect($document->refresh()->scan_status)->toBe(DocumentScanStatus::Indisponible)
        ->and($document->isDownloadable())->toBeTrue();
});

it('bloque un fichier infecté, met le dossier en incomplet et alerte les administrateurs', function () {
    Notification::fake();

    $admin = membreDuCabinet('admin');
    $application = Application::factory()->create(['status' => ApplicationStatus::Nouveau]);
    $document = documentSur($application, DocumentScanStatus::EnAttente);

    // On simule le verdict de ClamAV plutôt que d'exiger son installation.
    $document->forceFill(['scan_status' => DocumentScanStatus::Infecte, 'scanned_at' => now()])->save();
    $application->update(['status' => ApplicationStatus::Incomplet]);
    Notification::send(collect([$admin]), new InfectedDocumentFound($document));

    expect($document->refresh()->isDownloadable())->toBeFalse()
        ->and($application->refresh()->status)->toBe(ApplicationStatus::Incomplet);

    Notification::assertSentTo($admin, InfectedDocumentFound::class);

    // Et la route refuse de le servir.
    $this->actingAs(membreDuCabinet())
        ->get(route('documents.download', $document))
        ->assertForbidden();
});

it('n\'inclut jamais le fichier infecté dans l\'e-mail d\'alerte', function () {
    $document = documentSur(Application::factory()->create(), DocumentScanStatus::Infecte);

    $mail = (new InfectedDocumentFound($document))->toMail(membreDuCabinet('admin'));

    expect($mail->attachments)->toBeEmpty()
        ->and($mail->rawAttachments)->toBeEmpty()
        ->and((string) $mail->render())->toContain($document->application->reference);
});

it('planifie une analyse pour chaque pièce déposée', function () {
    Queue::fake();
    Notification::fake();

    $application = Application::factory()->create();
    Document::factory()->count(3)->create(['application_id' => $application->id]);

    foreach ($application->documents as $document) {
        ScanUploadedDocument::dispatch($document);
    }

    Queue::assertPushed(ScanUploadedDocument::class, 3);
});

/*
|--------------------------------------------------------------------------
| B.3 — L'archive ZIP ne sature pas la mémoire
|--------------------------------------------------------------------------
*/

it('assemble l\'archive sans charger les fichiers en mémoire', function () {
    $application = Application::factory()->create(['reference' => 'LN-2026-00042']);

    // Huit pièces d'un mégaoctet : le cas décrit par le cahier des charges.
    foreach (range(1, 8) as $index) {
        $document = Document::factory()->create([
            'application_id' => $application->id,
            'original_name' => "scan-{$index}.pdf",
            'path' => "documents/LN-2026-00042/piece-{$index}.pdf",
            'scan_status' => DocumentScanStatus::Sain,
        ]);

        Storage::disk(SubmitApplication::DISK)->put($document->path, str_repeat('A', 1024 * 1024));
    }

    $response = app(BuildApplicationArchive::class)($application);

    $avant = memory_get_usage();
    ob_start();
    $response->sendContent();
    $contenu = (string) ob_get_clean();
    $consomme = memory_get_usage() - $avant;

    expect(strlen($contenu))->toBeGreaterThan(0)
        // Les 8 Mo ne transitent jamais par la mémoire du processus.
        ->and($consomme)->toBeLessThan(2 * 1024 * 1024);

    $chemin = tempnam(sys_get_temp_dir(), 'ln-zip-');
    file_put_contents($chemin, $contenu);

    $zip = new ZipArchive;
    $zip->open($chemin);

    expect($zip->numFiles)->toBe(8);
    $zip->close();
    @unlink($chemin);
});

it('exclut du ZIP les pièces infectées', function () {
    $application = Application::factory()->create(['reference' => 'LN-2026-00043']);

    documentSur($application, DocumentScanStatus::Sain);
    documentSur($application, DocumentScanStatus::Infecte);

    $response = app(BuildApplicationArchive::class)($application);

    ob_start();
    $response->sendContent();
    $chemin = tempnam(sys_get_temp_dir(), 'ln-zip-');
    file_put_contents($chemin, (string) ob_get_clean());

    $zip = new ZipArchive;
    $zip->open($chemin);

    expect($zip->numFiles)->toBe(1);
    $zip->close();
    @unlink($chemin);
});

it('refuse de construire une archive vide', function () {
    $application = Application::factory()->create();
    documentSur($application, DocumentScanStatus::Infecte);

    expect(fn () => app(BuildApplicationArchive::class)($application))
        ->toThrow(RuntimeException::class);
});
