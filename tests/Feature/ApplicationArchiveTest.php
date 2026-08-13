<?php

use App\Actions\BuildApplicationArchive;
use App\Actions\SubmitApplication;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake(SubmitApplication::DISK);
});

function dossierAvecDocuments(): Application
{
    $application = Application::factory()->create(['reference' => 'LN-2026-00007']);

    $fichiers = [
        [DocumentType::Cv, 'mon-cv.pdf', 'contenu du cv'],
        [DocumentType::TcfTef, 'tcf.pdf', 'contenu du tcf'],
        [DocumentType::Diplome, 'licence.pdf', 'contenu licence'],
        [DocumentType::Diplome, 'master.pdf', 'contenu master'],
    ];

    foreach ($fichiers as [$type, $nom, $contenu]) {
        $path = 'documents/'.$application->reference.'/'.Str::uuid()->toString().'.pdf';
        Storage::disk(SubmitApplication::DISK)->put($path, $contenu);

        Document::factory()->create([
            'application_id' => $application->id,
            'type' => $type,
            'original_name' => $nom,
            'path' => $path,
        ]);
    }

    return $application;
}

it('assemble tous les documents dans une archive ZIP', function () {
    $application = dossierAvecDocuments();

    $response = app(BuildApplicationArchive::class)($application);

    expect($response->getFile()->getPathname())->toBeFile();

    $zip = new ZipArchive;
    expect($zip->open($response->getFile()->getPathname()))->toBeTrue()
        ->and($zip->numFiles)->toBe(4);

    $noms = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $noms[] = $zip->getNameIndex($i);
    }

    // Les diplômes multiples sont numérotés pour ne pas s'écraser.
    expect($noms)->toContain('cv-1 - mon-cv.pdf')
        ->and($noms)->toContain('diplome-1 - licence.pdf')
        ->and($noms)->toContain('diplome-2 - master.pdf');

    expect($zip->getFromName('cv-1 - mon-cv.pdf'))->toBe('contenu du cv');

    $zip->close();
});

it('nomme l\'archive avec la référence du dossier', function () {
    $application = dossierAvecDocuments();

    $response = app(BuildApplicationArchive::class)($application);

    expect($response->headers->get('content-disposition'))
        ->toContain('LN-2026-00007.zip');
});

it('assainit un nom de fichier tentant une traversée de chemin', function () {
    $application = Application::factory()->create(['reference' => 'LN-2026-00008']);

    $path = 'documents/'.$application->reference.'/'.Str::uuid()->toString().'.pdf';
    Storage::disk(SubmitApplication::DISK)->put($path, 'charge utile');

    Document::factory()->create([
        'application_id' => $application->id,
        'type' => DocumentType::Autre,
        'original_name' => '../../../etc/passwd',
        'path' => $path,
    ]);

    $response = app(BuildApplicationArchive::class)($application);

    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    expect($zip->getNameIndex(0))->not->toContain('..')
        ->and($zip->getNameIndex(0))->not->toContain('/etc/');

    $zip->close();
});

it('refuse de construire une archive vide', function () {
    $application = Application::factory()->create();

    expect(fn () => app(BuildApplicationArchive::class)($application))
        ->toThrow(RuntimeException::class);
});

it('journalise le téléchargement d\'un document avec son auteur', function () {
    Role::findOrCreate('agent', 'web');
    $agent = tap(User::factory()->create())->assignRole('agent');

    $application = dossierAvecDocuments();
    $document = $application->documents()->first();

    $this->actingAs($agent)
        ->get(route('documents.download', $document))
        ->assertOk();

    $trace = Activity::where('log_name', 'document')->sole();

    expect($trace->description)->toBe('Document téléchargé')
        ->and($trace->causer_id)->toBe($agent->id)
        ->and($trace->subject_id)->toBe($document->id)
        ->and($trace->properties['application'])->toBe('LN-2026-00007');
});
