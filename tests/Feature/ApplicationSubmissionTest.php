<?php

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Notifications\ApplicationReceived;
use App\Notifications\ApplicationSubmitted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Construit un véritable UploadedFile sur disque.
 *
 * UploadedFile::fake() déduit le type MIME du NOM du fichier : il ne permet
 * donc pas de vérifier que la règle mimes inspecte bien le contenu réel.
 * On écrit un vrai fichier temporaire pour tester le comportement de production.
 */
function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'ln-test-');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, test: true);
}

/**
 * Un PDF minimal mais authentique.
 */
function pdf(string $name): UploadedFile
{
    return realUpload(
        $name,
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        ."2 0 obj\n<< /Type /Pages /Count 0 /Kids [] >>\nendobj\n"
        ."trailer\n<< /Root 1 0 R >>\n%%EOF\n",
    );
}

/**
 * @return array<string, mixed>
 */
function validPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Aïcha',
        'last_name' => 'Nkolo',
        'email' => 'aicha.nkolo@example.cm',
        'phone' => '+237 6 99 88 77 66',
        'country_of_residence' => 'Cameroun',
        'target_program' => 'Entrée Express',
        'message' => 'Bonjour, je souhaite immigrer au Canada.',
        'cv' => pdf('mon-cv.pdf'),
        'tcf_tef' => pdf('resultat-tcf.pdf'),
    ], $overrides);
}

it('enregistre le dossier, stocke les documents sur le disque privé et notifie', function () {
    Storage::fake('local');
    Storage::fake('public');
    Notification::fake();

    $response = $this->post(route('depot.store'), validPayload([
        'diplomes' => [pdf('licence.pdf'), pdf('master.pdf')],
    ]));

    $response->assertRedirect(route('depot.confirmation'));

    // Le dossier
    $application = Application::sole();
    expect($application->reference)->toMatch('/^LN-\d{4}-\d{5}$/')
        ->and($application->status)->toBe(ApplicationStatus::Nouveau)
        ->and($application->full_name)->toBe('Aïcha Nkolo')
        ->and($application->ip_address)->not->toBeNull();

    // Une ligne par fichier, y compris plusieurs diplômes
    expect($application->documents)->toHaveCount(4);
    expect($application->documents->where('type', DocumentType::Diplome))->toHaveCount(2);

    foreach ($application->documents as $document) {
        // Sur le disque privé…
        Storage::disk('local')->assertExists($document->path);
        // …et nulle part sur le disque public.
        Storage::disk('public')->assertMissing($document->path);

        // Nom de fichier généré en UUID, le nom d'origine n'est qu'un libellé.
        expect(pathinfo($document->path, PATHINFO_FILENAME))
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        expect($document->path)->not->toContain($document->original_name);
    }

    expect($application->documents->firstWhere('type', DocumentType::Cv)->original_name)
        ->toBe('mon-cv.pdf');

    // Les deux notifications partent, et elles sont mises en file d'attente.
    Notification::assertSentOnDemand(ApplicationSubmitted::class);
    Notification::assertSentOnDemand(ApplicationReceived::class);
    expect(new ApplicationSubmitted($application))->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('refuse un exécutable renommé en .pdf', function () {
    Storage::fake('local');
    Notification::fake();

    // En-tête MZ d'un exécutable Windows, déguisé derrière une extension .pdf.
    $malware = realUpload(
        'mon-cv.pdf',
        "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00"
        .str_repeat("\x00", 48)."This program cannot be run in DOS mode.\x0D\x0A\x24".str_repeat("\x00", 256),
    );

    $response = $this->post(route('depot.store'), validPayload(['cv' => $malware]));

    $response->assertSessionHasErrors('cv');

    expect(Application::count())->toBe(0);
    Storage::disk('local')->assertDirectoryEmpty('/');
    Notification::assertNothingSent();
});

it('rejette un envoi dont le honeypot est rempli', function () {
    Notification::fake();

    $response = $this->post(route('depot.store'), validPayload(['website' => 'http://spam.example']));

    $response->assertSessionHasErrors('website');
    expect(Application::count())->toBe(0);
    Notification::assertNothingSent();
});

it('génère des références uniques et séquentielles', function () {
    Storage::fake('local');
    Notification::fake();

    $this->post(route('depot.store'), validPayload());
    $this->post(route('depot.store'), validPayload(['email' => 'second@example.cm']));

    $references = Application::orderBy('id')->pluck('reference')->all();

    expect($references)->toHaveCount(2)
        ->and($references[0])->toBe('LN-'.now()->year.'-00001')
        ->and($references[1])->toBe('LN-'.now()->year.'-00002');
});
