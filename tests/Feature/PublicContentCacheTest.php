<?php

use App\Enums\SectionType;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\TeamMember;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Le cache public ne doit contenir que des tableaux
|--------------------------------------------------------------------------
|
| Laravel fixe `cache.serializable_classes` à false : aucun objet PHP n'est
| désérialisé depuis le cache, ce qui ferme les chaînes de gadgets si l'APP_KEY
| fuite. Un modèle Eloquent mis en cache revient donc en __PHP_Incomplete_Class
| au deuxième appel, et la page part en 500.
|
| Le store `array` employé par défaut dans les tests ne sérialise rien : il ne
| peut pas voir ce défaut. On le force ici à se comporter comme celui de
| production — le premier appel remplit le cache, le second le relit.
|
*/

beforeEach(function () {
    $this->seed(SiteContentSeeder::class);

    Cache::swap(new Repository(new ArrayStore(
        serializesValues: true,
        serializableClasses: config('cache.serializable_classes'),
    )));

    $categorie = FaqCategory::factory()->create(['name' => 'Avant de déposer']);
    Faq::factory()->create([
        'faq_category_id' => $categorie->id,
        'question' => 'Quels documents dois-je préparer ?',
    ]);

    Service::factory()->published()->create([
        'slug' => 'entree-express',
        'title' => 'Entrée Express',
    ]);

    TeamMember::factory()->published()->create(['name' => 'Aïcha Nkolo']);

    PageSection::factory()->sur('accueil')->type(SectionType::Cta, [
        'titre' => 'Bloc mis en cache',
        'bouton_libelle' => 'Déposer mon dossier',
        'bouton_url' => '/deposer-mon-dossier',
    ])->create();
});

it('sert le même contenu une fois le cache chaud', function (string $route, string $attendu) {
    // Premier appel : le cache est froid, tout est lu en base.
    $this->get($route)->assertOk()->assertSee($attendu, false);

    // Second appel : tout est relu depuis le cache. C'est ici que la page
    // partait en 500, faute de pouvoir désérialiser les modèles.
    $this->get($route)->assertOk()->assertSee($attendu, false);
})->with([
    'accueil' => ['/', 'Bloc mis en cache'],
    'services' => ['/services', 'Entrée Express'],
    'fiche service' => ['/services/entree-express', 'Entrée Express'],
    'faq' => ['/faq', 'Quels documents dois-je préparer ?'],
    'à propos' => ['/a-propos', 'Aïcha Nkolo'],
    'plan du site' => ['/sitemap.xml', '/services/entree-express'],
]);

it('ne met aucun objet dans le cache public', function () {
    // On remplit le cache par le chemin normal.
    Service::publiés();
    TeamMember::publiés();
    FaqCategory::avecQuestions();
    PageSection::pour('accueil');

    $cles = [
        Service::publicCacheKey(),
        TeamMember::publicCacheKey(),
        FaqCategory::publicCacheKey(),
        PageSection::publicCacheKey(),
    ];

    foreach ($cles as $cle) {
        $valeur = Cache::get($cle);

        expect($valeur)->toBeArray("La clé {$cle} doit contenir un tableau, pas un objet.");
    }
});

it('restitue des modèles utilisables après relecture du cache', function () {
    // Premier appel : mise en cache. Second : relecture et réhydratation.
    Service::publiés();
    FaqCategory::avecQuestions();
    PageSection::pour('accueil');

    $service = Service::publiés()->first();

    expect($service)->toBeInstanceOf(Service::class)
        ->and($service->title)->toBe('Entrée Express')
        // Les casts doivent toujours s'appliquer après réhydratation.
        ->and($service->is_published)->toBeTrue();

    $categorie = FaqCategory::avecQuestions()->first();

    expect($categorie)->toBeInstanceOf(FaqCategory::class)
        // La relation est reconstruite à la main : elle doit survivre au cache.
        ->and($categorie->publishedFaqs)->toHaveCount(1)
        ->and($categorie->publishedFaqs->first()->question)->toBe('Quels documents dois-je préparer ?');

    $bloc = PageSection::pour('accueil')->first();

    expect($bloc)->toBeInstanceOf(PageSection::class)
        ->and($bloc->sectionType())->toBe(SectionType::Cta)
        // Le cast array du champ data doit tenir après réhydratation.
        ->and($bloc->valeur('titre'))->toBe('Bloc mis en cache');
});

it('invalide le cache même quand il sérialise', function () {
    $this->get('/services')->assertOk()->assertSee('Entrée Express');

    Service::query()->where('slug', 'entree-express')->first()
        ?->update(['title' => 'Entrée Express — révisé']);

    $this->get('/services')
        ->assertOk()
        ->assertSee('Entrée Express — révisé')
        ->assertDontSee('>Entrée Express<', false);
});
