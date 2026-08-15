<?php

use App\Enums\SectionType;
use App\Filament\Resources\FaqCategories\Pages\EditFaqCategory;
use App\Filament\Resources\FaqCategories\Pages\ListFaqCategories;
use App\Filament\Resources\PageSections\Pages\EditPageSection;
use App\Filament\Resources\PageSections\Pages\ListPageSections;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\SiteContents\Pages\EditSiteContent;
use App\Filament\Resources\TeamMembers\Pages\EditTeamMember;
use App\Filament\Resources\TeamMembers\Pages\ListTeamMembers;
use App\Models\FaqCategory;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\SiteContent;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\SiteContentSeeder;
use Filament\Forms\Components\FileUpload;
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

it('ne propose plus les champs remplacés par les tables dédiées', function () {
    $this->seed(SiteContentSeeder::class);

    // Services et questions ont leur propre table, en nombre libre. Laisser
    // « Service 1 », « Question 1 »… dans Textes du site enverrait la cliente
    // remplir des champs qui n'apparaissent nulle part.
    foreach (['services', 'faq'] as $bloc) {
        $cles = array_keys(SiteContent::query()->where('key', $bloc)->sole()->content);

        expect($cles)->not->toContain('service_1_titre')
            ->and($cles)->not->toContain('question_1')
            ->and($cles)->not->toContain('reponse_1')
            ->and($cles)->not->toContain('tarifs_texte');
    }

    // L'habillage de la page, lui, reste éditable.
    $faq = SiteContent::query()->where('key', 'faq')->sole()->content;

    expect($faq)->toHaveKeys(['titre', 'introduction', 'recherche_placeholder', 'vide', 'cta_titre']);
});

it('offre un téléversement, et non un champ texte, pour les images de page', function () {
    $this->seed(SiteContentSeeder::class);
    $this->actingAs(personnelContenu('admin'));

    $bloc = SiteContent::query()->where('key', 'accueil')->sole();

    // Un champ texte attendant un chemin de stockage serait inutilisable :
    // la cliente n'a aucun moyen de connaître ce chemin.
    Livewire::test(EditSiteContent::class, ['record' => $bloc->getRouteKey()])
        ->assertOk()
        ->assertFormFieldExists(
            'content.hero_image',
            checkFieldUsing: fn ($field): bool => $field instanceof FileUpload,
        );

    // Et la valeur livrée est vide, pas un placeholder : le téléverseur
    // croirait sinon avoir un fichier nommé « [À COMPLÉTER…] ».
    expect($bloc->content['hero_image'])->toBe('');
});

it('réunit les contenus sous « Contenu du site » dans la navigation', function () {
    $this->actingAs(personnelContenu('admin'))
        ->get('/admin')
        ->assertOk()
        ->assertSee('Contenu du site')
        ->assertSee('Questions fréquentes')
        ->assertSee('Composition des pages')
        ->assertSee('Équipe');
});

it('ne montre à un agent que les témoignages, en lecture', function () {
    // TestimonialPolicy autorise délibérément la consultation par un agent :
    // il peut avoir besoin de relire un témoignage, sans jamais publier au nom
    // du cabinet. Le reste de la vitrine lui reste fermé.
    $reponse = $this->actingAs(personnelContenu('agent'))->get('/admin')->assertOk();

    $reponse->assertSee('Témoignages')
        ->assertDontSee('Composition des pages')
        ->assertDontSee('Textes des pages')
        ->assertDontSee('Apparence');
});

it('sert au back-office un thème compilé, et non la feuille précompilée', function () {
    // C'est l'invariant qui compte : sans ce thème, aucun utilitaire Tailwind
    // écrit dans une vue du panel n'existe dans la CSS servie. Les icônes
    // s'affichent alors à leur taille intrinsèque et recouvrent la page.
    $this->actingAs(personnelContenu('admin'))
        ->get('/admin')
        ->assertOk()
        ->assertSee('/build/assets/theme-', false);
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
