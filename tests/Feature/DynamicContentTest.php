<?php

use App\Enums\SectionType;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\TeamMember;
use Database\Seeders\SiteContentSeeder;

beforeEach(function () {
    $this->seed(SiteContentSeeder::class);
});

/*
|--------------------------------------------------------------------------
| 1. Un service dépublié n'existe pas pour le public
|--------------------------------------------------------------------------
*/

it('masque un service dépublié, dans la liste comme sur sa page', function () {
    $publie = Service::factory()->published()->create([
        'slug' => 'entree-express',
        'title' => 'Entrée Express',
    ]);

    $brouillon = Service::factory()->create([
        'slug' => 'programme-en-brouillon',
        'title' => 'Programme en brouillon',
    ]);

    $liste = $this->get(route('services'));

    $liste->assertOk()
        ->assertSee($publie->title)
        ->assertDontSee($brouillon->title);

    $this->get(route('services.show', $publie))->assertOk()->assertSee($publie->title);

    // 404, et non une page vide qui laisserait croire à une erreur passagère.
    $this->get(route('services.show', $brouillon))->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| 2. Une catégorie dépubliée masque ses questions
|--------------------------------------------------------------------------
*/

it('masque les questions d\'un thème dépublié', function () {
    $visible = FaqCategory::factory()->create(['name' => 'Avant de déposer']);
    Faq::factory()->create([
        'faq_category_id' => $visible->id,
        'question' => 'Comment savoir si je suis admissible ?',
    ]);

    $masque = FaqCategory::factory()->unpublished()->create(['name' => 'Thème masqué']);
    Faq::factory()->create([
        'faq_category_id' => $masque->id,
        'question' => 'Cette question ne doit pas apparaître ?',
    ]);

    $reponse = $this->get(route('faq'));

    $reponse->assertOk()
        ->assertSee('Comment savoir si je suis admissible ?')
        ->assertDontSee('Thème masqué')
        ->assertDontSee('Cette question ne doit pas apparaître ?');
});

it('n\'affiche pas un thème dont aucune question n\'est publiée', function () {
    $categorie = FaqCategory::factory()->create(['name' => 'Thème sans question publiée']);
    Faq::factory()->unpublished()->create(['faq_category_id' => $categorie->id]);

    $this->get(route('faq'))
        ->assertOk()
        ->assertDontSee('Thème sans question publiée');
});

it('publie un balisage FAQPage à partir des questions visibles', function () {
    $categorie = FaqCategory::factory()->create();
    Faq::factory()->create([
        'faq_category_id' => $categorie->id,
        'question' => 'Quels documents dois-je préparer ?',
        'answer' => '<p>Votre CV et votre résultat TCF ou TEF.</p>',
    ]);

    $reponse = $this->get(route('faq'));

    $reponse->assertOk();

    // On isole le balisage : le corps de l'accordéon contient légitimement le
    // HTML de la réponse, ce qui rendrait une assertion sur toute la page vide
    // de sens.
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', (string) $reponse->getContent(), $trouve);

    expect($trouve)->not->toBeEmpty();

    $balisage = json_decode($trouve[1], true);

    expect($balisage)->toHaveKey('@type', 'FAQPage')
        ->and($balisage['mainEntity'])->toHaveCount(1)
        ->and($balisage['mainEntity'][0]['name'])->toBe('Quels documents dois-je préparer ?')
        // Le balisage transporte du texte brut, pas du HTML.
        ->and($balisage['mainEntity'][0]['acceptedAnswer']['text'])
        ->toBe('Votre CV et votre résultat TCF ou TEF.');
});

/*
|--------------------------------------------------------------------------
| 3. Une section dépubliée n'est pas rendue
|--------------------------------------------------------------------------
*/

it('ne rend pas un bloc dépublié', function () {
    PageSection::factory()->sur('accueil')->type(SectionType::Cta, [
        'titre' => 'Bloc bien visible',
        'bouton_libelle' => 'Déposer mon dossier',
        'bouton_url' => '/deposer-mon-dossier',
    ])->create();

    PageSection::factory()->sur('accueil')->unpublished()->type(SectionType::Cta, [
        'titre' => 'Bloc masqué',
    ])->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Bloc bien visible')
        ->assertDontSee('Bloc masqué');
});

it('respecte l\'ordre choisi et ignore un type inconnu', function () {
    PageSection::factory()->sur('accueil')->type(SectionType::Citation, [
        'texte' => 'Deuxième bloc',
    ])->create(['sort_order' => 2]);

    PageSection::factory()->sur('accueil')->type(SectionType::Cta, [
        'titre' => 'Premier bloc',
    ])->create(['sort_order' => 1]);

    // Un type retiré du code ne doit pas casser la page.
    PageSection::factory()->sur('accueil')->create([
        'sort_order' => 3,
        'type' => 'type_disparu',
        'data' => ['titre' => 'Bloc orphelin'],
    ]);

    $contenu = $this->get(route('home'))->assertOk()->getContent();

    expect(strpos($contenu, 'Premier bloc'))->toBeLessThan(strpos($contenu, 'Deuxième bloc'))
        ->and($contenu)->not->toContain('Bloc orphelin');
});

it('n\'affiche l\'équipe que si un membre est publié', function () {
    $this->get(route('a-propos'))->assertOk()->assertDontSee('Aïcha Nkolo');

    TeamMember::factory()->published()->create(['name' => 'Aïcha Nkolo', 'role' => 'Conseillère']);

    $this->get(route('a-propos'))->assertOk()->assertSee('Aïcha Nkolo');
});

/*
|--------------------------------------------------------------------------
| 4. Une sauvegarde invalide le cache
|--------------------------------------------------------------------------
*/

it('rend visible immédiatement un service modifié', function () {
    $service = Service::factory()->published()->create(['title' => 'Titre initial']);

    // Premier rendu : la liste est lue puis mise en cache.
    $this->get(route('services'))->assertOk()->assertSee('Titre initial');

    $service->update(['title' => 'Titre corrigé par la cliente']);

    $this->get(route('services'))
        ->assertOk()
        ->assertSee('Titre corrigé par la cliente')
        ->assertDontSee('Titre initial');
});

it('rend visible immédiatement une question ajoutée', function () {
    $categorie = FaqCategory::factory()->create();
    Faq::factory()->create(['faq_category_id' => $categorie->id, 'question' => 'Première question ?']);

    $this->get(route('faq'))->assertOk()->assertDontSee('Question ajoutée après coup ?');

    Faq::factory()->create([
        'faq_category_id' => $categorie->id,
        'question' => 'Question ajoutée après coup ?',
    ]);

    $this->get(route('faq'))->assertOk()->assertSee('Question ajoutée après coup ?');
});

/*
|--------------------------------------------------------------------------
| 5. Le sitemap contient les services publiés, et eux seuls
|--------------------------------------------------------------------------
*/

it('liste les services publiés dans le sitemap', function () {
    $publie = Service::factory()->published()->create(['slug' => 'entree-express']);
    $brouillon = Service::factory()->create(['slug' => 'programme-en-brouillon']);

    $reponse = $this->get(route('sitemap'));

    $reponse->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(route('services.show', $publie), false)
        ->assertDontSee(route('services.show', $brouillon), false);
});

/*
|--------------------------------------------------------------------------
| Aucun plafond codé
|--------------------------------------------------------------------------
*/

it('n\'impose aucune limite au nombre de contenus', function () {
    $categorie = FaqCategory::factory()->create();
    Faq::factory()->count(25)->create(['faq_category_id' => $categorie->id]);
    Service::factory()->count(15)->published()->create();

    expect(FaqCategory::avecQuestions()->first()->publishedFaqs)->toHaveCount(25)
        ->and(Service::publiés())->toHaveCount(15);

    $this->get(route('faq'))->assertOk();
    $this->get(route('services'))->assertOk();
});
