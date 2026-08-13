<?php

use App\Filament\Pages\Securite;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

function membre(string $role): User
{
    Role::findOrCreate($role, 'web');

    return tap(User::factory()->create())->assignRole($role);
}

/*
|--------------------------------------------------------------------------
| Fusion du tableau de bord et du back-office
|--------------------------------------------------------------------------
*/

it('renvoie l\'ancien tableau de bord vers le back-office', function () {
    $this->actingAs(membre('admin'))
        ->get(route('dashboard'))
        ->assertRedirect('/admin');
});

it('renvoie les anciennes pages de réglages vers le back-office', function (string $route, string $cible) {
    $this->actingAs(membre('admin'))
        ->get(route($route))
        ->assertRedirect($cible);
})->with([
    ['profile.edit', '/admin/profile'],
    ['security.edit', '/admin/securite'],
    ['appearance.edit', '/admin/profile'],
]);

it('conduit vers le back-office après la connexion', function () {
    $user = membre('admin');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/admin');

    $this->assertAuthenticated();
});

/*
|--------------------------------------------------------------------------
| Profil
|--------------------------------------------------------------------------
*/

it('affiche la page de profil dans le back-office', function () {
    $this->actingAs(membre('agent'))
        ->get('/admin/profile')
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Sécurité — la mécanique reste celle de Fortify
|--------------------------------------------------------------------------
*/

it('affiche la page de sécurité', function () {
    $this->actingAs(membre('agent'))
        ->get('/admin/securite')
        ->assertOk();
});

it('active, confirme puis désactive la double authentification', function () {
    // Un code TOTP ne vaut que 30 secondes. Sans horloge figée, le test échoue
    // au hasard lorsque la fenêtre bascule entre le calcul et la vérification.
    $this->freezeTime();

    $user = membre('admin');
    $this->actingAs($user);

    $page = Livewire::test(Securite::class);

    expect($page->instance()->deuxFacteursActive())->toBeFalse();

    // Activation : Fortify génère le secret, la confirmation reste à faire.
    $page->callAction('activer');
    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    // Confirmation avec un code valide calculé depuis le secret.
    $code = app(Google2FA::class)
        ->getCurrentOtp(decrypt($user->two_factor_secret));

    Livewire::test(Securite::class)->callAction('confirmer', ['code' => $code]);

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->recoveryCodes())->not->toBeEmpty();

    // Désactivation.
    Livewire::test(Securite::class)->callAction('desactiver');

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Comptes et rôles
|--------------------------------------------------------------------------
*/

it('réserve la gestion des comptes au rôle admin', function () {
    $this->actingAs(membre('agent'))
        ->get(UserResource::getUrl('index'))
        ->assertForbidden();

    $this->actingAs(membre('admin'))
        ->get(UserResource::getUrl('index'))
        ->assertOk();
});

it('crée un compte avec son rôle depuis le back-office', function () {
    Role::findOrCreate('agent', 'web');
    $roleAgent = Role::findByName('agent', 'web');

    Livewire::actingAs(membre('admin'))
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'Nouvelle Agente',
            'email' => 'agente@cabinet.cm',
            'password' => 'MotDePasse!2026',
            'roles' => [$roleAgent->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cree = User::where('email', 'agente@cabinet.cm')->sole();

    expect($cree->hasRole('agent'))->toBeTrue()
        ->and(Hash::check('MotDePasse!2026', $cree->password))->toBeTrue();
});

it('conserve le mot de passe quand le champ est laissé vide', function () {
    $cible = membre('agent');
    $motDePasseAvant = $cible->password;

    Livewire::actingAs(membre('admin'))
        ->test(EditUser::class, ['record' => $cible->getRouteKey()])
        ->fillForm(['name' => 'Nom Corrigé', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cible->refresh()->name)->toBe('Nom Corrigé')
        ->and($cible->password)->toBe($motDePasseAvant);
});

it('empêche un administrateur de supprimer son propre compte', function () {
    $admin = membre('admin');
    $autre = membre('admin');

    expect($admin->can('delete', $admin))->toBeFalse()
        ->and($admin->can('delete', $autre))->toBeTrue();
});

it('refuse l\'accès au back-office à un compte sans rôle', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});
