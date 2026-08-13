<?php

use App\Enums\ApplicationStatus;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Filament\Resources\SiteContents\Pages\EditSiteContent;
use App\Filament\Resources\SiteContents\SiteContentResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Application;
use App\Models\SiteContent;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

function staff(string $role): User
{
    Role::findOrCreate($role, 'web');

    return tap(User::factory()->create())->assignRole($role);
}

/*
|--------------------------------------------------------------------------
| Accès au panel
|--------------------------------------------------------------------------
*/

it('renvoie un visiteur anonyme vers la connexion', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('refuse un utilisateur authentifié sans rôle', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

it('refuse un compte dont l\'e-mail n\'est pas vérifié', function () {
    $user = User::factory()->unverified()->create();
    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('laisse entrer un admin et un agent', function (string $role) {
    $this->actingAs(staff($role))->get('/admin')->assertOk();
})->with(['admin', 'agent']);

/*
|--------------------------------------------------------------------------
| Dossiers
|--------------------------------------------------------------------------
*/

it('liste les dossiers avec leur statut', function () {
    Application::factory()->create([
        'reference' => 'LN-2026-00042',
        'status' => ApplicationStatus::EnCours,
    ]);

    $this->actingAs(staff('agent'))
        ->get(ApplicationResource::getUrl('index'))
        ->assertOk()
        ->assertSee('LN-2026-00042')
        ->assertSee(ApplicationStatus::EnCours->label());
});

it('filtre les dossiers par statut', function () {
    Application::factory()->create(['reference' => 'LN-2026-00001', 'status' => ApplicationStatus::Nouveau]);
    Application::factory()->create(['reference' => 'LN-2026-00002', 'status' => ApplicationStatus::Valide]);

    Livewire::actingAs(staff('agent'))
        ->test(ListApplications::class)
        ->assertCanSeeTableRecords(Application::all())
        ->filterTable('status', [ApplicationStatus::Valide->value])
        ->assertCanSeeTableRecords(Application::where('status', ApplicationStatus::Valide)->get())
        ->assertCanNotSeeTableRecords(Application::where('status', ApplicationStatus::Nouveau)->get());
});

it('permet à un agent de changer le statut et les notes internes', function () {
    $application = Application::factory()->create(['status' => ApplicationStatus::Nouveau]);

    Livewire::actingAs(staff('agent'))
        ->test(EditApplication::class, ['record' => $application->getRouteKey()])
        ->fillForm([
            'status' => ApplicationStatus::Valide->value,
            'internal_notes' => 'Dossier complet, transmis au conseiller.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Valide)
        ->and($application->internal_notes)->toBe('Dossier complet, transmis au conseiller.');
});

it('change le statut de plusieurs dossiers en une fois', function () {
    $dossiers = Application::factory()->count(3)->create(['status' => ApplicationStatus::Nouveau]);

    Livewire::actingAs(staff('agent'))
        ->test(ListApplications::class)
        ->callTableBulkAction('changer_statut', $dossiers, [
            'status' => ApplicationStatus::EnCours->value,
        ]);

    expect(Application::where('status', ApplicationStatus::EnCours)->count())->toBe(3);
});

it('réserve la suppression d\'un dossier au rôle admin', function () {
    $application = Application::factory()->create();

    expect(staff('agent')->can('delete', $application))->toBeFalse()
        ->and(staff('admin')->can('delete', $application))->toBeTrue();
});

it('journalise le dépôt et le changement de statut', function () {
    $application = Application::factory()->create(['status' => ApplicationStatus::Nouveau]);
    $agent = staff('agent');

    $this->actingAs($agent);
    $application->update(['status' => ApplicationStatus::Valide]);

    $journal = Activity::where('log_name', 'dossier')->get();

    expect($journal->pluck('description'))->toContain('Dossier déposé', 'Dossier modifié');

    $modification = $journal->firstWhere('description', 'Dossier modifié');
    expect($modification->causer_id)->toBe($agent->id)
        ->and($modification->properties['attributes']['status'])->toBe(ApplicationStatus::Valide->value);
});

/*
|--------------------------------------------------------------------------
| Textes du site
|--------------------------------------------------------------------------
*/

it('réserve les textes du site au rôle admin', function () {
    $bloc = SiteContent::factory()->key('accueil')->create();

    expect(staff('agent')->can('viewAny', SiteContent::class))->toBeFalse()
        ->and(staff('admin')->can('update', $bloc))->toBeTrue();

    $this->actingAs(staff('agent'))
        ->get(SiteContentResource::getUrl('index'))
        ->assertForbidden();
});

it('interdit la création et la suppression d\'un bloc', function () {
    $bloc = SiteContent::factory()->key('accueil')->create();
    $admin = staff('admin');

    expect(SiteContentResource::canCreate())->toBeFalse()
        ->and($admin->can('create', SiteContent::class))->toBeFalse()
        ->and($admin->can('delete', $bloc))->toBeFalse();
});

it('génère un champ par clé du bloc, sans jamais montrer de JSON', function () {
    $bloc = SiteContent::factory()->key('accueil', "Page d'accueil")->content([
        'hero_titre' => 'Titre court',
        'hero_texte' => 'Un paragraphe bien plus long que le seuil retenu pour basculer en zone de saisie multiligne.',
        'pied_html' => '<p>Mise en forme</p>',
    ])->create();

    Livewire::actingAs(staff('admin'))
        ->test(EditSiteContent::class, ['record' => $bloc->getRouteKey()])
        ->assertFormFieldExists('content.hero_titre')
        ->assertFormFieldExists('content.hero_texte')
        ->assertFormFieldExists('content.pied_html')
        ->assertSchemaStateSet([
            'content.hero_titre' => 'Titre court',
        ]);
});

it('rend visible sur le site public un texte modifié depuis le back-office', function () {
    $bloc = SiteContent::factory()->key('accueil', "Page d'accueil")->content([
        'hero_titre' => 'Avant modification',
    ])->create();

    $this->get(route('home'))->assertSee('Avant modification');

    Livewire::actingAs(staff('admin'))
        ->test(EditSiteContent::class, ['record' => $bloc->getRouteKey()])
        ->fillForm(['content.hero_titre' => 'Après modification'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('home'))
        ->assertSee('Après modification')
        ->assertDontSee('Avant modification');
});

/*
|--------------------------------------------------------------------------
| Témoignages
|--------------------------------------------------------------------------
*/

it('réserve la rédaction des témoignages au rôle admin', function () {
    $this->actingAs(staff('agent'))
        ->get(TestimonialResource::getUrl('create'))
        ->assertForbidden();

    $this->actingAs(staff('admin'))
        ->get(TestimonialResource::getUrl('create'))
        ->assertOk();
});
