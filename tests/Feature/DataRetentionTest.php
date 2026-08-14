<?php

use App\Actions\NotifyApplicant;
use App\Actions\PurgeExpiredApplications;
use App\Actions\SubmitApplication;
use App\Enums\ApplicationStatus;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\Document;
use App\Models\User;
use App\Notifications\ApplicationsNearingExpiry;
use Carbon\CarbonInterface;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

function equipe(string $role = 'admin'): User
{
    Role::findOrCreate($role, 'web');

    return tap(User::factory()->create())->assignRole($role);
}

function pdfReel(string $nom): UploadedFile
{
    $chemin = tempnam(sys_get_temp_dir(), 'ln-');
    file_put_contents($chemin, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

    return new UploadedFile($chemin, $nom, null, null, test: true);
}

/**
 * Crée un dossier avec une pièce sur le disque, et fige sa dernière activité.
 *
 * La date est passée telle quelle : exprimer l'ancienneté en mois entiers
 * masque les bornes exactes des fenêtres de purge et de préavis.
 */
function dossierDontActiviteRemonteA(CarbonInterface $activite, string $reference): Application
{
    $application = Application::factory()->create(['reference' => $reference]);

    $document = Document::factory()->create([
        'application_id' => $application->id,
        'path' => 'documents/'.$reference.'/'.Str::uuid()->toString().'.pdf',
    ]);

    Storage::disk(SubmitApplication::DISK)->put($document->path, 'scan de passeport');

    $application->forceFill([
        'created_at' => $activite,
        'updated_at' => $activite,
    ])->saveQuietly();

    return $application;
}

beforeEach(function () {
    Storage::fake(SubmitApplication::DISK);
});

/*
|--------------------------------------------------------------------------
| D.1 — Consentement
|--------------------------------------------------------------------------
*/

it('refuse un dépôt sans consentement, côté serveur', function () {
    Notification::fake();

    $reponse = $this->post(route('depot.store'), [
        'first_name' => 'Aïcha',
        'last_name' => 'Nkolo',
        'email' => 'aicha@example.cm',
        'phone' => '+237699887766',
        'country_of_residence' => 'Cameroun',
        'cv' => pdfReel('cv.pdf'),
        'tcf_tef' => pdfReel('tcf.pdf'),
        // La case n'est pas cochée : l'attribut « required » du navigateur se
        // contourne en trois clics, le serveur doit trancher lui-même.
    ]);

    $reponse->assertSessionHasErrors('consentement');
    expect(Application::count())->toBe(0);
});

it('enregistre la date du consentement et la version acceptée', function () {
    Notification::fake();
    config(['retention.privacy_version' => '2026-01']);

    $this->post(route('depot.store'), [
        'first_name' => 'Aïcha',
        'last_name' => 'Nkolo',
        'email' => 'aicha@example.cm',
        'phone' => '+237699887766',
        'country_of_residence' => 'Cameroun',
        'consentement' => '1',
        'cv' => pdfReel('cv.pdf'),
        'tcf_tef' => pdfReel('tcf.pdf'),
    ])->assertRedirect(route('depot.confirmation'));

    $application = Application::sole();

    expect($application->consented_at)->not->toBeNull()
        // Sans la version, on ne saurait pas à quoi le candidat a consenti.
        ->and($application->privacy_version)->toBe('2026-01');
});

it('affiche la case de consentement et le lien vers la politique', function () {
    $this->seed(SiteContentSeeder::class);

    $this->get(route('depot.create'))
        ->assertOk()
        ->assertSee('name="consentement"', false)
        ->assertSee('required', false)
        ->assertSee(route('confidentialite'), false);
});

/*
|--------------------------------------------------------------------------
| D.2 — Conservation des dossiers vivants
|--------------------------------------------------------------------------
*/

it('efface un dossier vivant resté sans activité 36 mois', function () {
    $abandonne = dossierDontActiviteRemonteA(now()->subMonths(40), 'LN-2023-00001');
    $chemin = $abandonne->documents()->first()->path;

    $resultat = app(PurgeExpiredApplications::class)();

    expect($resultat['inactifs'])->toBe(1)
        ->and(Application::withTrashed()->whereKey($abandonne->id)->exists())->toBeFalse();

    Storage::disk(SubmitApplication::DISK)->assertMissing($chemin);
});

it('épargne un dossier ancien mais toujours suivi', function () {
    $suivi = dossierDontActiviteRemonteA(now()->subMonths(40), 'LN-2023-00002');

    // Un message adressé au candidat le mois dernier : le dossier vit encore.
    app(NotifyApplicant::class)->handle(
        $suivi,
        ApplicationStatus::EnCours,
        'Nous reprenons contact avec vous.',
        false,
        equipe('agent'),
    );

    $suivi->forceFill(['updated_at' => now()->subMonths(40)])->saveQuietly();

    app(PurgeExpiredApplications::class)();

    expect(Application::whereKey($suivi->id)->exists())->toBeTrue();
});

it('épargne un dossier récent', function () {
    $recent = dossierDontActiviteRemonteA(now()->subMonths(6), 'LN-2026-00003');

    app(PurgeExpiredApplications::class)();

    expect(Application::whereKey($recent->id)->exists())->toBeTrue();
});

it('prévient les administrateurs 30 jours avant l\'échéance', function () {
    Notification::fake();

    $admin = equipe('admin');
    // Au coeur de la fenêtre : purgeable dans quinze jours, pas encore aujourd'hui.
    $bientot = dossierDontActiviteRemonteA(now()->subMonths(36)->addDays(15), 'LN-2023-00004');

    $this->artisan('ln:purge-applications')->assertSuccessful();

    // Le préavis part…
    Notification::assertSentTo(
        $admin,
        ApplicationsNearingExpiry::class,
        fn (ApplicationsNearingExpiry $notification): bool => $notification->applications
            ->contains(fn (Application $a): bool => $a->is($bientot)),
    );

    // …et le dossier est toujours là : c'est un préavis, pas un effacement.
    expect(Application::whereKey($bientot->id)->exists())->toBeTrue();
});

it('couvre les deux règles en mode simulation, sans rien effacer', function () {
    $abandonne = dossierDontActiviteRemonteA(now()->subMonths(40), 'LN-2023-00005');

    $supprime = Application::factory()->create(['reference' => 'LN-2025-00006']);
    $supprime->delete();
    $supprime->forceFill(['deleted_at' => now()->subDays(120)])->saveQuietly();

    $this->artisan('ln:purge-applications --dry-run')
        ->expectsOutputToContain('2 dossier(s) seraient supprimés')
        ->expectsOutputToContain('sans activité depuis 36 mois')
        ->assertSuccessful();

    // Rien n'a bougé.
    expect(Application::whereKey($abandonne->id)->exists())->toBeTrue()
        ->and(Application::onlyTrashed()->whereKey($supprime->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| D.3 — Effacement à la demande
|--------------------------------------------------------------------------
*/

it('efface un dossier à la demande, et n\'en garde que la trace au journal', function () {
    $application = dossierDontActiviteRemonteA(now()->subMonth(), 'LN-2026-00007');
    $chemin = $application->documents()->first()->path;

    Livewire::actingAs(equipe('admin'))
        ->test(ViewApplication::class, ['record' => $application->reference])
        ->callAction('effacer', ['confirmation' => 'LN-2026-00007']);

    expect(Application::withTrashed()->whereKey($application->id)->exists())->toBeFalse();
    Storage::disk(SubmitApplication::DISK)->assertMissing($chemin);

    // La seule trace qui subsiste, sans donnée personnelle.
    $trace = Activity::query()->where('description', 'Dossier effacé définitivement')->sole();

    expect($trace->properties['reference'])->toBe('LN-2026-00007')
        ->and($trace->properties['motif'])->toBe('Effacement à la demande')
        ->and($trace->causer_id)->not->toBeNull()
        ->and(json_encode($trace->properties))->not->toContain($application->email);
});

it('refuse l\'effacement si la référence recopiée ne correspond pas', function () {
    $application = dossierDontActiviteRemonteA(now()->subMonth(), 'LN-2026-00008');

    Livewire::actingAs(equipe('admin'))
        ->test(ViewApplication::class, ['record' => $application->reference])
        ->callAction('effacer', ['confirmation' => 'LN-2026-99999'])
        ->assertHasActionErrors(['confirmation']);

    expect(Application::whereKey($application->id)->exists())->toBeTrue();
});

it('réserve l\'effacement définitif au rôle admin', function () {
    $application = dossierDontActiviteRemonteA(now()->subMonth(), 'LN-2026-00009');

    expect(equipe('agent')->can('forceDelete', $application))->toBeFalse()
        ->and(equipe('admin')->can('forceDelete', $application))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| D.4 — Politique de confidentialité
|--------------------------------------------------------------------------
*/

it('publie une politique de confidentialité complète', function () {
    $this->seed(SiteContentSeeder::class);

    $reponse = $this->get(route('confidentialite'));

    $reponse->assertOk();

    foreach ([
        'Données collectées',
        'Pourquoi nous les collectons',
        'Qui a accès à vos données',
        'Durée de conservation',
        'Sécurité de vos documents',
        'Vos droits',
        'Nous écrire',
    ] as $section) {
        $reponse->assertSee($section);
    }

    // Les durées annoncées correspondent à ce que fait réellement le code.
    $reponse->assertSee('36 mois')->assertSee('90 jours');
});

it('laisse en clair ce que seule la cliente peut fournir', function () {
    $this->seed(SiteContentSeeder::class);

    // Aucune mention légale inventée : raison sociale, immatriculation,
    // hébergeur et autorité de contrôle restent à compléter.
    $this->get(route('confidentialite'))
        ->assertOk()
        ->assertSee('[À COMPLÉTER PAR LA CLIENTE]');
});
