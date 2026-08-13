<?php

use App\Models\SiteContent;
use Database\Seeders\SiteContentSeeder;

it('affiche sur la page publique le texte modifié et invalide le cache', function () {
    $bloc = SiteContent::factory()->key('accueil', "Page d'accueil")->content([
        'hero_titre' => 'Titre initial',
        'hero_bouton' => 'Déposer mon dossier',
    ])->create();

    // Premier rendu : le texte est lu puis mis en cache.
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Titre initial');

    // Modification depuis le back-office.
    $bloc->update(['content' => [
        'hero_titre' => 'Titre corrigé par la cliente',
        'hero_bouton' => 'Déposer mon dossier',
    ]]);

    // L'observer a vidé le cache : le nouveau texte apparaît immédiatement.
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Titre corrigé par la cliente')
        ->assertDontSee('Titre initial');
});

it('retombe sur la valeur par défaut quand la clé est absente', function () {
    expect(content('accueil.cle_inexistante', 'Valeur de repli'))->toBe('Valeur de repli');
    expect(content('bloc_inexistant.titre', 'Repli'))->toBe('Repli');
    expect(content('chemin_sans_point', 'Repli'))->toBe('Repli');
});

it('sert les textes de la locale courante', function () {
    SiteContent::factory()->key('accueil')->content(['hero_titre' => 'Bonjour'])->create(['locale' => 'fr']);
    SiteContent::factory()->key('accueil')->content(['hero_titre' => 'Hello'])->create(['locale' => 'en']);

    expect(content('accueil.hero_titre'))->toBe('Bonjour');

    app()->setLocale('en');

    expect(content('accueil.hero_titre'))->toBe('Hello');
});

it('crée les blocs du site sans écraser une modification existante', function () {
    $this->seed(SiteContentSeeder::class);

    $accueil = SiteContent::query()->where('key', 'accueil')->sole();
    $accueil->update(['content' => [...$accueil->content, 'hero_titre' => 'Texte de la cliente']]);

    // Relancer le seeder ne doit rien écraser.
    $this->seed(SiteContentSeeder::class);

    expect($accueil->fresh()->content['hero_titre'])->toBe('Texte de la cliente');
    expect(SiteContent::query()->where('key', 'accueil')->count())->toBe(1);
});
