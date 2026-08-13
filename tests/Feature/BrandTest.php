<?php

use App\Models\User;
use Database\Seeders\SiteContentSeeder;
use Spatie\Permission\Models\Role;

function administrateur(): User
{
    Role::findOrCreate('admin', 'web');

    return tap(User::factory()->create())->assignRole('admin');
}

/** Le premier point du tracé « L », signature du monogramme dans le HTML. */
const SIGNATURE_MONOGRAMME = 'points="6,9';

it('affiche le monogramme LN sur le site public', function (string $route) {
    $this->seed(SiteContentSeeder::class);

    $this->get(route($route))
        ->assertOk()
        ->assertSee(SIGNATURE_MONOGRAMME, false);
})->with(['home', 'services', 'contact']);

it('affiche le monogramme LN sur la page de connexion', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(SIGNATURE_MONOGRAMME, false);
});

it('affiche le monogramme LN dans le back-office', function () {
    $this->actingAs(administrateur())
        ->get('/admin')
        ->assertOk()
        ->assertSee(SIGNATURE_MONOGRAMME, false);
});

it('déclare le favicon dans le back-office', function () {
    // Sans cette déclaration, le navigateur retombe sur /favicon.ico,
    // qui était resté celui de Laravel.
    $this->actingAs(administrateur())
        ->get('/admin')
        ->assertOk()
        ->assertSee('rel="icon"', false)
        ->assertSee('favicon.svg', false);
});

it('ne laisse plus aucune trace de la marque Laravel', function () {
    $this->seed(SiteContentSeeder::class);

    // Le tracé du logo Laravel d'origine.
    $this->get(route('home'))->assertDontSee('M17.2 5.633', false);
    $this->get(route('login'))->assertDontSee('M17.2 5.633', false);

    // Les fichiers d'icônes ne sont plus ceux du dépôt d'origine.
    expect(file_get_contents(public_path('favicon.svg')))->toContain('polygon');
});

it('bascule sur le logo de la cliente dès qu\'un fichier est configuré', function () {
    $this->seed(SiteContentSeeder::class);

    // Un seul réglage suffit à changer le logo partout.
    config(['brand.logo' => 'images/logo-definitif.svg']);

    $accueil = $this->get(route('home'));

    $accueil->assertOk()
        ->assertSee('images/logo-definitif.svg', false)
        ->assertDontSee(SIGNATURE_MONOGRAMME, false);

    $this->actingAs(administrateur())
        ->get('/admin')
        ->assertOk()
        ->assertSee('images/logo-definitif.svg', false);
});

it('régénère les trois fichiers d\'icônes depuis la même source', function () {
    $avant = collect(['favicon.svg', 'favicon.ico', 'apple-touch-icon.png'])
        ->mapWithKeys(fn (string $fichier): array => [$fichier => md5_file(public_path($fichier))]);

    $this->artisan('ln:generate-icons')->assertSuccessful();

    foreach ($avant as $fichier => $empreinte) {
        // Régénérés à l'identique tant que la configuration ne change pas.
        expect(md5_file(public_path($fichier)))->toBe($empreinte);
    }

    // Le SVG reprend bien les coordonnées de config/brand.php.
    expect(file_get_contents(public_path('favicon.svg')))
        ->toContain('6,9')
        ->toContain((string) config('brand.icone_fond'));
});
