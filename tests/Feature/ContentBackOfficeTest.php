<?php

use App\Enums\SectionType;
use App\Filament\Resources\FaqCategories\Pages\EditFaqCategory;
use App\Filament\Resources\FaqCategories\Pages\ListFaqCategories;
use App\Filament\Resources\PageSections\Pages\EditPageSection;
use App\Filament\Resources\PageSections\Pages\ListPageSections;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\TeamMembers\Pages\EditTeamMember;
use App\Filament\Resources\TeamMembers\Pages\ListTeamMembers;
use App\Models\FaqCategory;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\TeamMember;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Un compte du personnel doté du rôle demandé.
 *
 * Nom distinct du helper de AdminPanelTest : deux fonctions globales de même
 * nom se marcheraient dessus selon l'ordre de chargement des fichiers.
 */
function personnelContenu(string $role): User
{
    Role::findOrCreate($role, 'web');

    return tap(User::factory()->create())->assignRole($role);
}

/*
|--------------------------------------------------------------------------
| Accès : la vitrine est réservée à l'administratrice
|--------------------------------------------------------------------------
|
| Un agent traite les dossiers ; il ne touche pas au contenu du site.
|
*/

it('ouvre les listes de contenu pour un admin', function (string $page) {
    $this->actingAs(personnelContenu('admin'));

    Livewire::test($page)->assertOk();
})->with([
    'services' => ListServices::class,
    'questions fréquentes' => ListFaqCategories::class,
    'blocs de page' => ListPageSections::class,
    'équipe' => ListTeamMembers::class,
]);

it('réunit les contenus sous « Contenu du site » dans la navigation', function () {
    $this->actingAs(personnelContenu('admin'))
        ->get('/admin')
        ->assertOk()
        ->assertSee('Contenu du site')
        ->assertSee('Questions fréquentes')
        ->assertSee('Composition des pages')
        ->assertSee('Équipe');
});

it('cache le contenu du site à un agent', function () {
    $this->actingAs(personnelContenu('agent'))
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('Contenu du site')
        ->assertDontSee('Composition des pages');
});

it('affiche le logo du back-office à taille contrainte', function () {
    // Le panel sert sa CSS précompilée : une classe Tailwind du site n'y
    // existe pas. Sans dimension explicite, le monogramme recouvre la
    // navigation. Les styles en ligne sont donc voulus, pas un oubli.
    $this->actingAs(personnelContenu('admin'))
        ->get('/admin')
        ->assertOk()
        ->assertSee('width: 1rem', false);
});

it('refuse l\'accès au contenu à un agent', function (string $url) {
    $this->actingAs(personnelContenu('agent'))
        ->get($url)
        ->assertForbidden();
})->with([
    'services' => '/admin/services',
    'questions fréquentes' => '/admin/faq-categories',
    'blocs de page' => '/admin/page-sections',
    'équipe' => '/admin/team-members',
]);

/*
|--------------------------------------------------------------------------
| Le formulaire des blocs est construit selon le type choisi
|--------------------------------------------------------------------------
*/

it('affiche le formulaire de chaque type de bloc', function (SectionType $type) {
    $this->actingAs(personnelContenu('admin'));

    $bloc = PageSection::factory()->sur('accueil')->type($type)->create();

    Livewire::test(EditPageSection::class, ['record' => $bloc->getRouteKey()])
        ->assertOk()
        ->assertFormSet(['type' => $type->value]);
})->with(SectionType::cases());

it('garde la liste consultable si un type a disparu du code', function () {
    $this->actingAs(personnelContenu('admin'));

    PageSection::factory()->sur('accueil')->create([
        'type' => 'type_disparu',
        'data' => ['titre' => 'Bloc orphelin'],
    ]);

    Livewire::test(ListPageSections::class)->assertOk();
});

/*
|--------------------------------------------------------------------------
| Une écriture depuis le back-office se voit tout de suite en ligne
|--------------------------------------------------------------------------
*/

it('publie un service créé depuis le back-office', function () {
    $this->actingAs(personnelContenu('admin'));

    Livewire::test(CreateService::class)
        ->fillForm([
            'title' => 'Permis de travail',
            'slug' => 'permis-de-travail',
            'summary' => 'Les autorisations de travail temporaire.',
            'is_published' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('services', ['slug' => 'permis-de-travail']);

    $this->get(route('services'))->assertOk()->assertSee('Permis de travail');
});

it('répercute une modification faite depuis le back-office', function () {
    $service = Service::factory()->published()->create(['title' => 'Titre initial']);

    $this->get(route('services'))->assertOk()->assertSee('Titre initial');

    $this->actingAs(personnelContenu('admin'));

    Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
        ->fillForm(['title' => 'Titre corrigé'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->get(route('services'))
        ->assertOk()
        ->assertSee('Titre corrigé')
        ->assertDontSee('Titre initial');
});

it('ouvre les fiches d\'édition des autres contenus', function () {
    $this->actingAs(personnelContenu('admin'));

    $categorie = FaqCategory::factory()->create();
    $membre = TeamMember::factory()->published()->create();

    Livewire::test(EditFaqCategory::class, ['record' => $categorie->getRouteKey()])->assertOk();
    Livewire::test(EditTeamMember::class, ['record' => $membre->getRouteKey()])->assertOk();
});
