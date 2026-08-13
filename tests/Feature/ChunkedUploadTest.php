<?php

use App\Actions\SubmitApplication;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Support\TemporaryUploadStorage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(SubmitApplication::DISK);
    Notification::fake();
});

/**
 * Ouvre un transfert et renvoie le jeton délivré par le serveur.
 */
function ouvrirTransfert(object $test, string $name, int $length): string
{
    $response = $test->call('POST', '/televersement', server: [
        'HTTP_UPLOAD_LENGTH' => (string) $length,
        'HTTP_UPLOAD_NAME' => $name,
    ]);

    $response->assertOk();

    return trim($response->getContent());
}

/**
 * Envoie un contenu par tranches, comme le fait FilePond.
 */
function envoyerParTranches(object $test, string $token, string $contenu, int $taille = 16): void
{
    foreach (str_split($contenu, $taille) as $index => $tranche) {
        $test->call('PATCH', "/televersement?patch={$token}", server: [
            'HTTP_UPLOAD_OFFSET' => (string) ($index * $taille),
            'HTTP_UPLOAD_LENGTH' => (string) strlen($contenu),
        ], content: $tranche)->assertNoContent();
    }
}

function pdfContent(): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        ."2 0 obj\n<< /Type /Pages /Count 0 /Kids [] >>\nendobj\n"
        ."trailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

it('assemble un fichier envoyé par tranches', function () {
    $contenu = pdfContent();
    $token = ouvrirTransfert($this, 'cv.pdf', strlen($contenu));

    expect($token)->toMatch('/^[0-9a-f-]{36}$/');

    envoyerParTranches($this, $token, $contenu);

    $storage = app(TemporaryUploadStorage::class);
    $file = $storage->toUploadedFile($token);

    expect($file)->not->toBeNull()
        ->and($file->getClientOriginalName())->toBe('cv.pdf')
        ->and(file_get_contents($file->getPathname()))->toBe($contenu);
});

it('indique l\'avancement pour permettre la reprise après coupure', function () {
    $contenu = pdfContent();
    $token = ouvrirTransfert($this, 'cv.pdf', strlen($contenu));

    // Rien n'est encore parti.
    $this->call('GET', "/televersement?patch={$token}")
        ->assertOk()
        ->assertHeader('Upload-Offset', '0');

    // Une seule tranche part, puis le réseau coupe.
    $this->call('PATCH', "/televersement?patch={$token}", server: [
        'HTTP_UPLOAD_OFFSET' => '0',
    ], content: substr($contenu, 0, 16))->assertNoContent();

    // FilePond redemande l'avancement pour reprendre au bon endroit.
    $this->call('GET', "/televersement?patch={$token}")
        ->assertOk()
        ->assertHeader('Upload-Offset', '16');

    // La reprise complète le fichier.
    $this->call('PATCH', "/televersement?patch={$token}", server: [
        'HTTP_UPLOAD_OFFSET' => '16',
    ], content: substr($contenu, 16))->assertNoContent();

    $file = app(TemporaryUploadStorage::class)->toUploadedFile($token);
    expect(file_get_contents($file->getPathname()))->toBe($contenu);
});

it('dépose un dossier complet à partir de jetons FilePond', function () {
    $contenu = pdfContent();

    $cv = ouvrirTransfert($this, 'mon-cv.pdf', strlen($contenu));
    envoyerParTranches($this, $cv, $contenu);

    $tcf = ouvrirTransfert($this, 'tcf.pdf', strlen($contenu));
    envoyerParTranches($this, $tcf, $contenu);

    $diplome = ouvrirTransfert($this, 'licence.pdf', strlen($contenu));
    envoyerParTranches($this, $diplome, $contenu);

    $response = $this->post(route('depot.store'), [
        'first_name' => 'Aïcha',
        'last_name' => 'Nkolo',
        'email' => 'aicha@example.cm',
        'phone' => '+237 6 99 88 77 66',
        'country_of_residence' => 'Cameroun',
        // FilePond renvoie des jetons, pas des fichiers.
        'cv' => $cv,
        'tcf_tef' => $tcf,
        'diplomes' => [$diplome],
    ]);

    $response->assertRedirect(route('depot.confirmation'));

    $application = Application::sole();
    expect($application->documents)->toHaveCount(3);

    foreach ($application->documents as $document) {
        Storage::disk(SubmitApplication::DISK)->assertExists($document->path);
    }

    expect($application->documents->firstWhere('type', DocumentType::Cv)->original_name)
        ->toBe('mon-cv.pdf');

    // Les fichiers temporaires consommés ne traînent pas sur le disque.
    Storage::disk(SubmitApplication::DISK)
        ->assertDirectoryEmpty(TemporaryUploadStorage::DIRECTORY);
});

it('rejette un exécutable même envoyé par tranches', function () {
    $malware = "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00"
        .str_repeat("\x00", 48)."This program cannot be run in DOS mode.\x0D\x0A\x24".str_repeat("\x00", 256);

    $cv = ouvrirTransfert($this, 'mon-cv.pdf', strlen($malware));
    envoyerParTranches($this, $cv, $malware, 64);

    $pdf = pdfContent();
    $tcf = ouvrirTransfert($this, 'tcf.pdf', strlen($pdf));
    envoyerParTranches($this, $tcf, $pdf);

    $response = $this->post(route('depot.store'), [
        'first_name' => 'Aïcha',
        'last_name' => 'Nkolo',
        'email' => 'aicha@example.cm',
        'phone' => '+237 699887766',
        'country_of_residence' => 'Cameroun',
        'cv' => $cv,
        'tcf_tef' => $tcf,
    ]);

    $response->assertSessionHasErrors('cv');
    expect(Application::count())->toBe(0);
});

it('refuse un jeton appartenant à une autre session', function () {
    $contenu = pdfContent();
    $token = ouvrirTransfert($this, 'cv.pdf', strlen($contenu));
    envoyerParTranches($this, $token, $contenu);

    // Le visiteur change de session : le jeton ne vaut plus rien pour lui.
    $this->flushSession();

    expect(app(TemporaryUploadStorage::class)->toUploadedFile($token))
        ->toBeNull();

    $this->call('GET', "/televersement?patch={$token}")->assertNotFound();
});

it('ne laisse pas les requêtes de téléversement épuiser le quota du formulaire', function () {
    $contenu = pdfContent();

    // Un dossier réel représente déjà des dizaines de requêtes de tranches.
    $cv = ouvrirTransfert($this, 'cv.pdf', strlen($contenu));
    envoyerParTranches($this, $cv, $contenu, 4);

    $tcf = ouvrirTransfert($this, 'tcf.pdf', strlen($contenu));
    envoyerParTranches($this, $tcf, $contenu, 4);

    // La soumission doit rester possible : les limiteurs sont indépendants.
    $this->post(route('depot.store'), [
        'first_name' => 'Aïcha',
        'last_name' => 'Nkolo',
        'email' => 'aicha@example.cm',
        'phone' => '+237 699887766',
        'country_of_residence' => 'Cameroun',
        'cv' => $cv,
        'tcf_tef' => $tcf,
    ])->assertRedirect(route('depot.confirmation'));
});

it('refuse un fichier dont la taille annoncée dépasse la limite', function () {
    $this->call('POST', '/televersement', server: [
        'HTTP_UPLOAD_LENGTH' => (string) (TemporaryUploadStorage::MAX_BYTES + 1),
        'HTTP_UPLOAD_NAME' => 'enorme.pdf',
    ])->assertStatus(413);
});

it('refuse une tranche qui déborde de la taille annoncée', function () {
    $token = ouvrirTransfert($this, 'cv.pdf', 32);

    $this->call('PATCH', "/televersement?patch={$token}", server: [
        'HTTP_UPLOAD_OFFSET' => '0',
    ], content: str_repeat('A', 64))->assertStatus(422);
});

it('refuse un jeton qui n\'est pas un UUID', function () {
    $this->call('PATCH', '/televersement?patch=../../etc/passwd', server: [
        'HTTP_UPLOAD_OFFSET' => '0',
    ], content: 'x')->assertStatus(422);

    // Rien n'a été écrit : aucun répertoire de transfert n'existe.
    expect(Storage::disk(SubmitApplication::DISK)->directories(TemporaryUploadStorage::DIRECTORY))
        ->toBeEmpty();
});

it('purge les téléversements abandonnés et consommés', function () {
    $contenu = pdfContent();

    // Un transfert que le candidat abandonne.
    $abandonne = ouvrirTransfert($this, 'abandonne.pdf', strlen($contenu));
    envoyerParTranches($this, $abandonne, $contenu);

    // Le lendemain, un autre visiteur commence un transfert.
    $this->travel(25)->hours();

    $recent = ouvrirTransfert($this, 'recent.pdf', strlen($contenu));
    envoyerParTranches($this, $recent, $contenu);

    $this->artisan('uploads:purge-temporary')->assertSuccessful();

    $storage = app(TemporaryUploadStorage::class);

    // L'abandonné disparaît, celui en cours est préservé.
    expect($storage->toUploadedFile($abandonne))->toBeNull()
        ->and($storage->toUploadedFile($recent))->not->toBeNull();
});
