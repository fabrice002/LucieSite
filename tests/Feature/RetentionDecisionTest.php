<?php

use App\Actions\DecideApplicationRetention;
use App\Actions\PurgeExpiredApplications;
use App\Enums\ApplicationStatus;
use App\Enums\RetentionState;
use App\Filament\Pages\DossiersEnAttente;
use App\Filament\Widgets\PendingRetentionBanner;
use App\Models\Application;
use App\Models\ApplicationUpdate;
use App\Models\Document;
use App\Models\User;
use App\Notifications\ApplicationsAwaitingDecision;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

function adminConservation(): User
{
    Role::findOrCreate('admin', 'web');

    return tap(User::factory()->create())->assignRole('admin');
}

/**
 * Un dossier dont l'échéance de conservation est déjà dépassée.
 */
function dossierEchu(array $attributs = []): Application
{
    $application = Application::factory()->create($attributs);

    // On force l'échéance sans passer par une écriture ordinaire : l'observateur
    // la repousserait, puisqu'une modification vaut activité.
    $application->timestamps = false;
    $application->retention_due_at = now()->subDay();
    $application->save();

    return $application->refresh();
}

/*
|--------------------------------------------------------------------------
| 1. À l'échéance, on signale — on ne supprime pas
|--------------------------------------------------------------------------
*/

it('bascule un dossier échu en attente de décision sans rien supprimer', function () {
    Storage::fake('local');

    $application = dossierEchu();
    Document::factory()->for($application)->create(['path' => 'documents/'.$application->reference.'/cv.pdf']);

    Storage::disk('local')->put('documents/'.$application->reference.'/cv.pdf', 'contenu');

    app(PurgeExpiredApplications::class)();

    $application->refresh();

    expect($application->retention_state)->toBe(RetentionState::EnAttenteDeDecision)
        ->and($application->exists)->toBeTrue()
        ->and($application->trashed())->toBeFalse();

    // Le point qui compte : le fichier est intact.
    Storage::disk('local')->assertExists('documents/'.$application->reference.'/cv.pdf');

    $this->assertDatabaseHas('applications', ['id' => $application->id]);
});

it('journalise le passage en attente de décision', function () {
    $application = dossierEchu();

    app(PurgeExpiredApplications::class)();

    expect(Activity::query()->where('description', 'Dossier arrivé à échéance, en attente de décision')->exists())
        ->toBeTrue();
});

it('ne signale pas un dossier dont l\'échéance n\'est pas atteinte', function () {
    $application = Application::factory()->create();

    app(PurgeExpiredApplications::class)();

    expect($application->refresh()->retention_state)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 2. « Conserver 12 mois » repousse et retire de la file
|--------------------------------------------------------------------------
*/

it('repousse l\'échéance de douze mois et vide la file', function () {
    $application = dossierEchu();

    app(PurgeExpiredApplications::class)();

    expect(PurgeExpiredApplications::enAttenteDeDecision()->count())->toBe(1);

    app(DecideApplicationRetention::class)->conserver($application->refresh());

    $application->refresh();

    expect($application->retention_state)->toBeNull()
        ->and($application->retention_due_at->isAfter(now()->addMonths(11)))->toBeTrue()
        ->and($application->retention_due_at->isBefore(now()->addMonths(13)))->toBeTrue()
        ->and(PurgeExpiredApplications::enAttenteDeDecision()->count())->toBe(0);
});

it('ne fait pas repasser un dossier conservé à l\'échéance dès le lendemain', function () {
    $application = dossierEchu();

    app(PurgeExpiredApplications::class)();
    app(DecideApplicationRetention::class)->conserver($application->refresh());

    // Un second passage de la commande ne doit pas le remettre dans la file :
    // c'est le piège qu'aurait créé un sursis calculé sur la seule activité.
    app(PurgeExpiredApplications::class)();

    expect($application->refresh()->retention_state)->toBeNull();
});

it('consigne la décision de conservation', function () {
    $application = dossierEchu();

    app(DecideApplicationRetention::class)->conserver($application);

    expect(Activity::query()->where('description', 'Conservation prolongée de 12 mois')->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 3. La commande n'efface rien qui attende une décision
|--------------------------------------------------------------------------
*/

it('n\'efface jamais un dossier en attente de décision', function () {
    Storage::fake('local');

    $application = dossierEchu();

    // Trois passages, à des dates éloignées : rien ne doit disparaître.
    foreach ([0, 1, 2] as $_) {
        app(PurgeExpiredApplications::class)();
    }

    $this->travel(2)->years();

    app(PurgeExpiredApplications::class)();

    $this->assertDatabaseHas('applications', ['id' => $application->id]);

    expect($application->refresh()->retention_state)->toBe(RetentionState::EnAttenteDeDecision);
});

it('efface un dossier dont l\'effacement a été décidé, fichiers compris', function () {
    Storage::fake('local');

    $application = dossierEchu();
    $document = Document::factory()->for($application)->create([
        'path' => 'documents/'.$application->reference.'/passeport.pdf',
    ]);

    Storage::disk('local')->put($document->path, 'scan');

    app(DecideApplicationRetention::class)->effacer($application, app(PurgeExpiredApplications::class));

    $this->assertDatabaseMissing('applications', ['id' => $application->id]);
    $this->assertDatabaseMissing('documents', ['id' => $document->id]);

    Storage::disk('local')->assertMissing($document->path);

    expect(Activity::query()->where('description', 'Dossier effacé définitivement')->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'Effacement définitif demandé')->exists())->toBeTrue();
});

it('efface toujours les dossiers supprimés depuis plus de 90 jours', function () {
    Storage::fake('local');

    $application = Application::factory()->create();
    $application->delete();
    $application->timestamps = false;
    $application->deleted_at = now()->subDays(100);
    $application->save();

    app(PurgeExpiredApplications::class)();

    $this->assertDatabaseMissing('applications', ['id' => $application->id]);
});

/*
|--------------------------------------------------------------------------
| 4. Le bandeau : présent tant que la file n'est pas vide
|--------------------------------------------------------------------------
*/

it('affiche le bandeau dès qu\'un dossier attend, et le retire ensuite', function () {
    $this->actingAs(adminConservation());

    expect(PendingRetentionBanner::canView())->toBeFalse();

    $application = dossierEchu();
    app(PurgeExpiredApplications::class)();

    expect(PendingRetentionBanner::canView())->toBeTrue()
        ->and(Livewire::test(PendingRetentionBanner::class)->instance()->nombre())->toBe(1);

    app(DecideApplicationRetention::class)->conserver($application->refresh());

    expect(PendingRetentionBanner::canView())->toBeFalse();
});

it('ouvre la file d\'attente pour un admin et la refuse à un agent', function () {
    $this->actingAs(adminConservation());

    dossierEchu();
    app(PurgeExpiredApplications::class)();

    Livewire::test(DossiersEnAttente::class)->assertOk();

    expect(DossiersEnAttente::getNavigationBadge())->toBe('1');

    Role::findOrCreate('agent', 'web');
    $agent = tap(User::factory()->create())->assignRole('agent');

    $this->actingAs($agent)->get('/admin/dossiers-en-attente')->assertForbidden();
});

it('permet de trancher depuis la file', function () {
    $this->actingAs(adminConservation());

    $application = dossierEchu();
    app(PurgeExpiredApplications::class)();

    Livewire::test(DossiersEnAttente::class)
        ->callAction(TestAction::make('conserver')->table($application));

    expect($application->refresh()->retention_state)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Activité et relances
|--------------------------------------------------------------------------
*/

it('repousse l\'échéance dès qu\'on intervient sur le dossier', function () {
    $application = dossierEchu();

    $application->update(['status' => ApplicationStatus::EnCours]);

    expect($application->refresh()->retention_due_at->isFuture())->toBeTrue();

    app(PurgeExpiredApplications::class)();

    expect($application->refresh()->retention_state)->toBeNull();
});

it('compte un message au candidat comme une activité', function () {
    $application = dossierEchu();

    ApplicationUpdate::factory()->for($application)->create();

    expect($application->refresh()->retention_due_at->isFuture())->toBeTrue();
});

it('relance les administrateurs tous les mois, sans jamais s\'arrêter', function () {
    Notification::fake();

    adminConservation();
    dossierEchu();

    $this->artisan('ln:purge-applications')->assertSuccessful();

    Notification::assertSentTimes(ApplicationsAwaitingDecision::class, 1);

    // Le lendemain : pas de second envoi, on ne noie pas la boîte.
    $this->travel(1)->days();
    $this->artisan('ln:purge-applications')->assertSuccessful();

    Notification::assertSentTimes(ApplicationsAwaitingDecision::class, 1);

    // Un mois plus tard, la relance revient. Et elle reviendra toujours.
    $this->travel(32)->days();
    $this->artisan('ln:purge-applications')->assertSuccessful();

    Notification::assertSentTimes(ApplicationsAwaitingDecision::class, 2);
});

it('n\'assimile pas un rappel à une activité', function () {
    Notification::fake();

    adminConservation();
    $application = dossierEchu();

    $this->artisan('ln:purge-applications')->assertSuccessful();

    // Le rappel ne doit pas repousser l'échéance qu'il signale, sinon le
    // dossier sortirait de la file sans qu'aucune décision n'ait été prise.
    expect($application->refresh()->retention_state)->toBe(RetentionState::EnAttenteDeDecision)
        ->and($application->retention_reminded_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| --dry-run n'écrit rien
|--------------------------------------------------------------------------
*/

it('annonce les bascules sans rien modifier en simulation', function () {
    $application = dossierEchu();

    $this->artisan('ln:purge-applications --dry-run')
        ->expectsOutputToContain('basculeraient en attente de décision')
        ->assertSuccessful();

    expect($application->refresh()->retention_state)->toBeNull();
});
