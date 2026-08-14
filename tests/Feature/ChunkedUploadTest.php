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
 *
 * Reproduit fidèlement FilePond : la requête d'ouverture ne transporte QUE
 * l'en-tête Upload-Length (filepond.js, envoi du « process »). Le nom du
 * fichier n'y figure pas.
 */
function ouvrirTransfert(object $test, int $length): string
{
    $response = $test->call('POST', '/televersement', server: [
        'HTTP_UPLOAD_LENGTH' => (string) $length,
    ]);

    $response->assertOk();

    return trim($response->getContent());
}

/**
 * Envoie un contenu par tranches, comme le fait FilePond.
 *
 * C'est ici, et seulement ici, que le nom d'origine du fichier est transmis
 * (filepond.js, en-têtes des « chunks »).
 */
function envoyerParTranches(object $test, string $token, string $contenu, string $name, int $taille = 16): void
{
    foreach (str_split($contenu, $taille) as $index => $tranche) {
        $test->call('PATCH', "/televersement?patch={$token}", server: [
            'HTTP_UPLOAD_OFFSET' => (string) ($index * $taille),
            'HTTP_UPLOAD_LENGTH' => (string) strlen($contenu),
            'HTTP_UPLOAD_NAME' => $name,
        ], content: $tranche)->assertNoContent();
    }
}

/**
 * Raccourci : ouvre un transfert et envoie tout le contenu.
 */
function televerser(object $test, string $name, string $contenu, int $taille = 16): string
{
    $token = ouvrirTransfert($test, strlen($contenu));
    envoyerParTranches($test, $token, $contenu, $name, $taille);

    return $token;
}

function pdfContent(): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        ."2 0 obj\n<< /Type /Pages /Count 0 /Kids [] >>\nendobj\n"
        ."trailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function candidat(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Aïcha',
        'last_name' => 'Nkolo',
        'email' => 'aicha@example.cm',
        'phone' => '+237 699887766',
        'country_of_residence' => 'Cameroun',
        // Le consentement est obligatoire et vérifié côté serveur.
        'consentement' => '1',
    ], $overrides);
}

it('assemble un fichier envoyé par tranches', function () {
    $contenu = pdfContent();
    $token = televerser($this, 'cv.pdf', $contenu);

    expect($token)->toMatch('/^[0-9a-f-]{36}$/');

    $file = app(TemporaryUploadStorage::class)->toUploadedFile($token);

    expect($file)->not->toBeNull()
        ->and($file->getClientOriginalName())->toBe('cv.pdf')
        ->and(file_get_contents($file->getPathname()))->toBe($contenu);
});

it('indique l\'avancement pour permettre la reprise après coupure', function () {
    $contenu = pdfContent();
    $token = ouvrirTransfert($this, strlen($contenu));

    // Rien n'est encore parti.
    $this->call('GET', "/televersement?patch={$token}")
        ->assertOk()
        ->assertHeader('Upload-Offset', '0');

    // Une seule tranche part, puis le réseau coupe.
    $this->call('PATCH', "/televersement?patch={$token}", server: [
        'HTTP_UPLOAD_OFFSET' => '0',
        'HTTP_UPLOAD_NAME' => 'cv.pdf',
    ], content: substr($contenu, 0, 16))->assertNoContent();

    // FilePond redemande l'avancement pour reprendre au bon endroit.
    $this->call('GET', "/televersement?patch={$token}")
        ->assertOk()
        ->assertHeader('Upload-Offset', '16');

    // La reprise complète le fichier.
    $this->call('PATCH', "/televersement?patch={$token}", server: [
        'HTTP_UPLOAD_OFFSET' => '16',
        'HTTP_UPLOAD_NAME' => 'cv.pdf',
    ], content: substr($contenu, 16))->assertNoContent();

    $file = app(TemporaryUploadStorage::class)->toUploadedFile($token);

    expect(file_get_contents($file->getPathname()))->toBe($contenu)
        ->and($file->getClientOriginalName())->toBe('cv.pdf');
});

it('dépose un dossier complet à partir de jetons FilePond', function () {
    $contenu = pdfContent();

    $cv = televerser($this, 'mon-cv.pdf', $contenu);
    $tcf = televerser($this, 'tcf.pdf', $contenu);
    $diplome = televerser($this, 'licence.pdf', $contenu);

    $response = $this->post(route('depot.store'), candidat([
        // FilePond renvoie des jetons, pas des fichiers.
        'cv' => $cv,
        'tcf_tef' => $tcf,
        'diplomes' => [$diplome],
    ]));

    $response->assertRedirect(route('depot.confirmation'));

    $application = Application::sole();
    expect($application->documents)->toHaveCount(3);

    foreach ($application->documents as $document) {
        Storage::disk(SubmitApplication::DISK)->assertExists($document->path);

        // Le nom d'origine survit au transfert par tranches, extension comprise.
        expect($document->original_name)->toEndWith('.pdf')
            ->and($document->path)->toEndWith('.pdf');
    }

    expect($application->documents->firstWhere('type', DocumentType::Cv)->original_name)
        ->toBe('mon-cv.pdf');

    // Les fichiers temporaires consommés ne traînent pas sur le disque.
    Storage::disk(SubmitApplication::DISK)
        ->assertDirectoryEmpty(TemporaryUploadStorage::DIRECTORY);
});

it('rejette un exécutable même envoyé par tranches', function () {
    $malware = "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00"
        .str_repeat("\x00", 48).'This program cannot be run in DOS mode.'."\x0D\x0A\x24".str_repeat("\x00", 256);

    $cv = televerser($this, 'mon-cv.pdf', $malware, 64);
    $tcf = televerser($this, 'tcf.pdf', pdfContent());

    $this->post(route('depot.store'), candidat(['cv' => $cv, 'tcf_tef' => $tcf]))
        ->assertSessionHasErrors('cv');

    expect(Application::count())->toBe(0);
});

it('refuse un jeton appartenant à une autre session', function () {
    $token = televerser($this, 'cv.pdf', pdfContent());

    // Le visiteur change de session : le jeton ne vaut plus rien pour lui.
    $this->flushSession();

    expect(app(TemporaryUploadStorage::class)->toUploadedFile($token))->toBeNull();

    $this->call('GET', "/televersement?patch={$token}")->assertNotFound();
});

it('ne laisse pas les requêtes de téléversement épuiser le quota du formulaire', function () {
    $contenu = pdfContent();

    // Un dossier réel représente déjà des dizaines de requêtes de tranches.
    $cv = televerser($this, 'cv.pdf', $contenu, 4);
    $tcf = televerser($this, 'tcf.pdf', $contenu, 4);

    // La soumission doit rester possible : les limiteurs sont indépendants.
    $this->post(route('depot.store'), candidat(['cv' => $cv, 'tcf_tef' => $tcf]))
        ->assertRedirect(route('depot.confirmation'));
});

it('refuse un fichier dont la taille annoncée dépasse la limite', function () {
    $this->call('POST', '/televersement', server: [
        'HTTP_UPLOAD_LENGTH' => (string) (TemporaryUploadStorage::MAX_BYTES + 1),
    ])->assertStatus(413);
});

it('refuse une tranche qui déborde de la taille annoncée', function () {
    $token = ouvrirTransfert($this, 32);

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

it('assainit un nom de fichier tentant une traversée de chemin', function () {
    $token = televerser($this, '../../../evil.pdf', pdfContent());

    $file = app(TemporaryUploadStorage::class)->toUploadedFile($token);

    // Le nom est conservé pour l'affichage, mais débarrassé de tout chemin.
    expect($file->getClientOriginalName())->toBe('evil.pdf');
});

it('purge les téléversements abandonnés et consommés', function () {
    $contenu = pdfContent();

    // Un transfert que le candidat abandonne.
    $abandonne = televerser($this, 'abandonne.pdf', $contenu);

    // Le lendemain, un autre visiteur commence un transfert.
    $this->travel(25)->hours();

    $recent = televerser($this, 'recent.pdf', $contenu);

    $this->artisan('uploads:purge-temporary')->assertSuccessful();

    $storage = app(TemporaryUploadStorage::class);

    // L'abandonné disparaît, celui en cours est préservé.
    expect($storage->toUploadedFile($abandonne))->toBeNull()
        ->and($storage->toUploadedFile($recent))->not->toBeNull();
});
