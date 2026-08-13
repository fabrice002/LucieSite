<?php

use App\Actions\PurgeExpiredApplications;
use App\Actions\SubmitApplication;
use App\Models\Application;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake(SubmitApplication::DISK);
});

function dossierSupprimeDepuis(int $jours, string $reference): Application
{
    $application = Application::factory()->create(['reference' => $reference]);

    $path = 'documents/'.$reference.'/'.Str::uuid()->toString().'.pdf';
    Storage::disk(SubmitApplication::DISK)->put($path, 'scan de passeport');

    Document::factory()->create([
        'application_id' => $application->id,
        'path' => $path,
    ]);

    $application->delete();
    $application->forceFill(['deleted_at' => now()->subDays($jours)])->saveQuietly();

    return $application;
}

it('efface définitivement les dossiers supprimés depuis plus de 90 jours', function () {
    $ancien = dossierSupprimeDepuis(120, 'LN-2026-00001');
    $cheminAncien = $ancien->documents()->first()->path;

    $resultat = app(PurgeExpiredApplications::class)();

    expect($resultat)->toBe(['dossiers' => 1, 'fichiers' => 1]);

    // La ligne a disparu, y compris de la corbeille.
    expect(Application::withTrashed()->whereKey($ancien->id)->exists())->toBeFalse()
        ->and(Document::where('application_id', $ancien->id)->exists())->toBeFalse();

    // Et le scan n'est plus sur le disque.
    Storage::disk(SubmitApplication::DISK)->assertMissing($cheminAncien);
});

it('épargne un dossier supprimé récemment', function () {
    $recent = dossierSupprimeDepuis(30, 'LN-2026-00002');
    $chemin = $recent->documents()->first()->path;

    $resultat = app(PurgeExpiredApplications::class)();

    expect($resultat['dossiers'])->toBe(0)
        ->and(Application::withTrashed()->whereKey($recent->id)->exists())->toBeTrue();

    Storage::disk(SubmitApplication::DISK)->assertExists($chemin);
});

it('épargne un dossier actif, même ancien', function () {
    $actif = Application::factory()->create([
        'reference' => 'LN-2025-00003',
        'created_at' => now()->subYears(2),
    ]);

    app(PurgeExpiredApplications::class)();

    expect(Application::whereKey($actif->id)->exists())->toBeTrue();
});

it('supprime aussi le répertoire du dossier sur le disque', function () {
    $ancien = dossierSupprimeDepuis(200, 'LN-2025-00004');

    expect(Storage::disk(SubmitApplication::DISK)->directories('documents'))
        ->toContain('documents/LN-2025-00004');

    app(PurgeExpiredApplications::class)();

    expect(Storage::disk(SubmitApplication::DISK)->directories('documents'))
        ->not->toContain('documents/LN-2025-00004');
});

it('expose une commande artisan, avec un mode simulation', function () {
    dossierSupprimeDepuis(120, 'LN-2026-00005');

    // Le mode simulation annonce sans rien effacer.
    $this->artisan('ln:purge-applications --dry-run')
        ->expectsOutputToContain('1 dossier(s) seraient supprimés')
        ->assertSuccessful();

    expect(Application::onlyTrashed()->count())->toBe(1);

    $this->artisan('ln:purge-applications')
        ->expectsOutputToContain('1 dossier(s) et 1 fichier(s) supprimés')
        ->assertSuccessful();

    expect(Application::withTrashed()->count())->toBe(0);
});
