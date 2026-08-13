<?php

use App\Models\SiteContent;
use App\Models\Testimonial;
use Database\Seeders\SiteContentSeeder;

beforeEach(function () {
    $this->seed(SiteContentSeeder::class);
});

it('répond sur toutes les pages publiques', function (string $route) {
    $this->get(route($route))->assertOk();
})->with([
    'home', 'services', 'a-propos', 'temoignages', 'faq', 'contact',
    'depot.create', 'suivi.index', 'mentions-legales', 'confidentialite',
]);

it('présente la même navigation sur chaque page', function (string $route) {
    $response = $this->get(route($route));

    foreach (['services', 'a-propos', 'temoignages', 'faq', 'contact'] as $lien) {
        $response->assertSee(route($lien));
    }

    // Pied de page commun
    $response->assertSee(route('mentions-legales'))
        ->assertSee(route('confidentialite'));
})->with(['home', 'services', 'faq', 'contact', 'suivi.index']);

it('expose un titre et une description sur chaque page', function (string $route) {
    $this->get(route($route))
        ->assertSee('<link rel="canonical"', false)
        ->assertSee('property="og:title"', false);
})->with(['home', 'services', 'a-propos', 'faq', 'contact']);

it('permet de basculer entre thème clair et sombre', function () {
    $response = $this->get(route('home'));

    // La bascule est présente et écrit dans la même clé que le back-office,
    // pour que le choix suive le visiteur jusqu'à l'espace d'administration.
    $response->assertSee('data-theme-toggle', false)
        ->assertSee('flux.appearance', false);
});

it('n\'affiche plus la marque Laravel sur le site public ni à la connexion', function () {
    $this->get(route('home'))->assertDontSee('Laravel');

    // Le tracé du logo Laravel a disparu de la page de connexion.
    $this->get(route('login'))->assertDontSee('M17.2 5.633', false);
});

it('affiche les témoignages publiés et masque les brouillons', function () {
    Testimonial::factory()->published()->create(['author_name' => 'Aïcha Nkolo']);
    Testimonial::factory()->create(['author_name' => 'Brouillon Invisible']);

    $this->get(route('temoignages'))
        ->assertOk()
        ->assertSee('Aïcha Nkolo')
        ->assertDontSee('Brouillon Invisible');
});

it('affiche un message quand aucun témoignage n\'est publié', function () {
    $this->get(route('temoignages'))
        ->assertOk()
        ->assertSee(content('temoignages.aucun'));
});

it('publie un sitemap listant les pages publiques', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee(route('home'), false)
        ->assertSee(route('services'), false)
        ->assertSee(route('depot.create'), false);

    // Aucune page d'administration ne doit s'y trouver.
    expect($response->getContent())->not->toContain('/admin');
});

it('rend tous les textes modifiables depuis le back-office', function () {
    // Chaque page vitrine possède son bloc éditable.
    $blocs = SiteContent::query()->pluck('key')->all();

    expect($blocs)->toContain(
        'global', 'accueil', 'services', 'a_propos', 'temoignages',
        'faq', 'contact', 'depot', 'confirmation', 'suivi',
        'mentions_legales', 'confidentialite',
    );
});

it('reflète immédiatement un texte modifié sur la page concernée', function () {
    SiteContent::query()->where('key', 'services')->sole()->update([
        'content' => ['titre' => 'Nos prestations sur mesure'],
    ]);

    $this->get(route('services'))
        ->assertOk()
        ->assertSee('Nos prestations sur mesure');
});
