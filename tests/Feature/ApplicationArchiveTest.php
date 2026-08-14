<?php

use App\Actions\BuildApplicationArchive;
use App\Actions\SubmitApplication;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

/**
 * Récupère l'archive diffusée en flux et l'écrit dans un fichier lisible.
 *
 * L'action ne produit plus de fichier : c'est tout l'intérêt du flux. Pour
 * inspecter son contenu, il faut donc capturer la sortie.
 */
function archiveRecue(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();
    $contenu = (string) ob_get_clean();

    $chemin = tempnam(sys_get_temp_dir(), 'ln-zip-');
    file_put_contents($chemin, $contenu);

    return $chemin;
}

it('assemble tous les documents dans une archive ZIP', function () {
    $application = dossierAvecDocuments();

    $chemin = archiveRecue(app(BuildApplicationArchive::class)($application));

    $zip = new ZipArchive;
    expect($zip->open($chemin))->toBeTrue()
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
    @unlink($chemin);
});

it('diffuse l\'archive en flux, sans fichier temporaire ni mémoire', function () {
    $application = Application::factory()->create(['reference' => 'LN-2026-00042']);

    // Huit pièces d'un mégaoctet. Contenu aléatoire, donc incompressible :
    // avec des octets répétés, le ZIP ferait quelques kilo-octets et la mesure
    // de mémoire ne prouverait rien. Un scan photographié est de toute façon
    // déjà compressé.
    foreach (range(1, 8) as $index) {
        $document = Document::factory()->create([
            'application_id' => $application->id,
            'original_name' => "scan-{$index}.pdf",
            'path' => "documents/LN-2026-00042/piece-{$index}.pdf",
        ]);

        Storage::disk(SubmitApplication::DISK)->put($document->path, random_bytes(1024 * 1024));
    }

    $response = app(BuildApplicationArchive::class)($application);

    // La réponse est un flux : rien n'a encore été lu ni compressé.
    expect($response)->toBeInstanceOf(StreamedResponse::class);

    // On consomme le flux au fil de l'eau sans le conserver : un ob_start()
    // ordinaire retiendrait les 8 Mo et fausserait complètement la mesure.
    $octets = 0;
    $avant = memory_get_usage();

    ob_start(function (string $tranche) use (&$octets): string {
        $octets += strlen($tranche);

        return '';
    }, 65536);

    $response->sendContent();
    ob_end_flush();

    $consomme = memory_get_usage() - $avant;

    // Les 8 Mo sortent bien…
    expect($octets)->toBeGreaterThan(7 * 1024 * 1024)
        // …sans jamais transiter par la mémoire du processus.
        ->and($consomme)->toBeLessThan(2 * 1024 * 1024);
});

it('nomme l\'archive avec la référence du dossier', function () {
    $application = dossierAvecDocuments();

    $response = app(BuildApplicationArchive::class)($application);

    expect($response->headers->get('content-disposition'))
        ->toContain('LN-2026-00007.zip')
        ->toStartWith('attachment')
        ->and($response->headers->get('content-type'))->toBe('application/zip');
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

    $chemin = archiveRecue(app(BuildApplicationArchive::class)($application));

    $zip = new ZipArchive;
    $zip->open($chemin);

    expect($zip->getNameIndex(0))->not->toContain('..')
        ->and($zip->getNameIndex(0))->not->toContain('/etc/');

    $zip->close();
    @unlink($chemin);
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
