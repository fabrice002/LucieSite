<?php

use App\Filament\Pages\Apparence;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Couleur;
use App\Support\Palettes;
use App\Support\SiteSettingRepository;
use App\Support\ThemePublic;
use Database\Seeders\SiteContentSeeder;
use Filament\Notifications\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function personnelApparence(string $role): User
{
    Role::findOrCreate($role, 'web');

    return tap(User::factory()->create())->assignRole($role);
}

beforeEach(function () {
    $this->seed(SiteContentSeeder::class);
});

/*
|--------------------------------------------------------------------------
| 1. La couleur enregistrée arrive dans la page publique
|--------------------------------------------------------------------------
*/

it('applique la couleur enregistrée dans la variable CSS de la page publique', function () {
    app(SiteSettingRepository::class)->set('couleur_principale', '#166534');

    $reponse = $this->get(route('home'));

    $reponse->assertOk()
        ->assertSee('--color-brand:#166534', false);
});

it('recalcule les nuances dérivées de la couleur principale', function () {
    app(SiteSettingRepository::class)->set('couleur_principale', '#881337');

    $contenu = (string) $this->get(route('home'))->getContent();

    // Le survol est plus foncé, le fond doux beaucoup plus clair : la cliente
    // ne règle qu'une couleur, la déclinaison est calculée.
    expect($contenu)->toContain('--color-brand-hover:'.Couleur::assombrir('#881337', 0.15))
        ->and($contenu)->toContain('--color-brand-soft:'.Couleur::eclaircir('#881337', 0.92));
});

it('rend visible immédiatement un changement de couleur', function () {
    $reglages = app(SiteSettingRepository::class);

    $reglages->set('couleur_principale', '#166534');
    $this->get(route('home'))->assertOk()->assertSee('--color-brand:#166534', false);

    $reglages->set('couleur_principale', '#881337');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('--color-brand:#881337', false)
        ->assertDontSee('--color-brand:#166534', false);
});

/*
|--------------------------------------------------------------------------
| 2. Un contraste insuffisant est signalé
|--------------------------------------------------------------------------
*/

it('avertit quand le texte ne contraste pas assez avec la couleur principale', function () {
    $this->actingAs(personnelApparence('admin'));

    // Jaune vif, texte blanc : le cas d'école de l'illisible.
    Livewire::test(Apparence::class)
        ->fillForm([
            'couleur_principale' => '#facc15',
            'couleur_texte_sur_principale' => '#ffffff',
        ])
        ->call('enregistrer');

    Notification::assertNotified();

    // Enregistré malgré tout : c'est son site, elle décide.
    expect(setting('couleur_principale'))->toBe('#facc15');
});

it('n\'avertit pas quand le contraste est suffisant', function () {
    $this->actingAs(personnelApparence('admin'));

    Livewire::test(Apparence::class)
        ->fillForm([
            'couleur_principale' => '#1d4ed8',
            'couleur_texte_sur_principale' => '#ffffff',
        ])
        ->call('enregistrer');

    expect(Couleur::contrasteSuffisant('#1d4ed8', '#ffffff'))->toBeTrue();
});

it('calcule le rapport de contraste selon la formule WCAG', function () {
    // Repères connus : noir sur blanc vaut 21:1, une couleur sur elle-même 1:1.
    expect(Couleur::contraste('#000000', '#ffffff'))->toEqualWithDelta(21.0, 0.01)
        ->and(Couleur::contraste('#1d4ed8', '#1d4ed8'))->toEqualWithDelta(1.0, 0.01)
        ->and(Couleur::contrasteSuffisant('#facc15', '#ffffff'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 3. Une palette remplit toutes les couleurs
|--------------------------------------------------------------------------
*/

it('remplit toutes les couleurs quand une palette est choisie', function () {
    $this->actingAs(personnelApparence('admin'));

    $attendu = Palettes::couleurs('vert_foret');

    Livewire::test(Apparence::class)
        ->fillForm(['palette' => 'vert_foret'])
        ->assertFormSet($attendu)
        ->call('enregistrer');

    foreach ($attendu as $cle => $valeur) {
        expect(setting($cle))->toBe($valeur);
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('--color-brand:'.$attendu['couleur_principale'], false);
});

it('ne propose que des palettes lisibles', function (string $identifiant) {
    $couleurs = Palettes::couleurs($identifiant);

    expect($couleurs)->not->toBeNull();

    $ratio = Couleur::contraste(
        $couleurs['couleur_principale'],
        $couleurs['couleur_texte_sur_principale'],
    );

    expect($ratio)->toBeGreaterThanOrEqual(Couleur::CONTRASTE_MINIMUM);
})->with(array_keys(Palettes::toutes()));

/*
|--------------------------------------------------------------------------
| 4. Sans rien en base, l'apparence livrée s'applique
|--------------------------------------------------------------------------
*/

it('retombe sur config/brand.php quand la table est vide', function () {
    expect(SiteSetting::query()->count())->toBe(0)
        ->and(setting('couleur_principale'))->toBe(config('brand.apparence.couleur_principale'))
        ->and(setting('police'))->toBe(config('brand.apparence.police'));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('--color-brand:'.config('brand.apparence.couleur_principale'), false);
});

it('ignore une couleur mal formée plutôt que de casser la feuille', function () {
    app(SiteSettingRepository::class)->set('couleur_principale', 'bleu foncé');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('--color-brand:'.config('brand.apparence.couleur_principale'), false);
});

/*
|--------------------------------------------------------------------------
| Police et thème sombre
|--------------------------------------------------------------------------
*/

it('ne charge que la police choisie', function () {
    app(SiteSettingRepository::class)->set('police', 'lora');

    $contenu = (string) $this->get(route('home'))->getContent();

    expect($contenu)->toContain('Lora')
        // Les autres familles ne sont ni préchargées ni déclarées : sur 3G,
        // chaque fichier compte.
        ->and($contenu)->not->toContain('public-sans')
        ->and($contenu)->not->toContain('instrument-sans');
});

it('retombe sur la police livrée si celle réglée n\'existe plus', function () {
    app(SiteSettingRepository::class)->set('police', 'comic-sans-ms');

    expect(app(ThemePublic::class)->police())->toBe(array_key_first(config('brand.polices')));

    $this->get(route('home'))->assertOk();
});

it('retire la bascule de thème quand le thème sombre est désactivé', function () {
    $this->get(route('home'))->assertOk()->assertSee('data-theme-toggle', false);

    app(SiteSettingRepository::class)->set('theme_sombre_actif', false);

    $reponse = $this->get(route('home'));

    $reponse->assertOk()
        ->assertDontSee('data-theme-toggle', false)
        // Sans thème sombre, aucune règle .dark n'est injectée.
        ->assertDontSee('.dark{', false);
});

/*
|--------------------------------------------------------------------------
| Accès
|--------------------------------------------------------------------------
*/

it('réserve la page Apparence au rôle admin', function () {
    $this->actingAs(personnelApparence('agent'))
        ->get('/admin/apparence')
        ->assertForbidden();

    $this->actingAs(personnelApparence('admin'))
        ->get('/admin/apparence')
        ->assertOk();
});
