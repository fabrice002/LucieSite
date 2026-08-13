<?php

use App\Enums\ApplicationStatus;
use App\Filament\Pages\ChangerMotDePasse;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Application;
use App\Models\User;
use App\Notifications\StaffAccountCreated;
use App\Support\ApplicationHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function personnel(string $role): User
{
    Role::findOrCreate($role, 'web');

    return tap(User::factory()->create())->assignRole($role);
}

/*
|--------------------------------------------------------------------------
| Création d'un compte : e-mail de bienvenue
|--------------------------------------------------------------------------
*/

it('envoie un e-mail de bienvenue quand un administrateur crée un compte', function () {
    Notification::fake();
    Role::findOrCreate('agent', 'web');
    $roleAgent = Role::findByName('agent', 'web');

    Livewire::actingAs(personnel('admin'))
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

    Notification::assertSentTo($cree, StaffAccountCreated::class);

    // L'e-mail part en file : le candidat comme le personnel n'attendent
    // jamais le SMTP.
    expect(new StaffAccountCreated($cree, 'agent'))->toBeInstanceOf(ShouldQueue::class);
});

it('ne transmet jamais le mot de passe provisoire par e-mail', function () {
    $user = User::factory()->create(['name' => 'Agente']);

    $mail = (new StaffAccountCreated($user, 'agent'))->toMail($user);
    $corps = implode(' ', array_map(
        fn (mixed $ligne): string => is_string($ligne) ? $ligne : '',
        $mail->introLines + $mail->outroLines,
    ));

    expect($corps)->not->toContain('MotDePasse')
        ->and($corps)->toContain($user->email);
});

it('envoie aussi un e-mail depuis la commande artisan', function () {
    Notification::fake();

    $this->artisan('ln:create-user', [
        '--name' => 'Lucie N.',
        '--email' => 'lucie@cabinet.cm',
        '--role' => 'admin',
    ])->expectsQuestion('Mot de passe', 'MotDePasse!2026')
        ->assertSuccessful();

    $cree = User::where('email', 'lucie@cabinet.cm')->sole();

    Notification::assertSentTo($cree, StaffAccountCreated::class);
    expect($cree->must_change_password)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Changement de mot de passe imposé
|--------------------------------------------------------------------------
*/

it('marque le mot de passe comme provisoire à la création', function () {
    Role::findOrCreate('agent', 'web');
    $roleAgent = Role::findByName('agent', 'web');

    Livewire::actingAs(personnel('admin'))
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'Agente',
            'email' => 'provisoire@cabinet.cm',
            'password' => 'MotDePasse!2026',
            'roles' => [$roleAgent->id],
        ])
        ->call('create');

    expect(User::where('email', 'provisoire@cabinet.cm')->sole()->must_change_password)
        ->toBeTrue();
});

it('renvoie vers le changement de mot de passe tant qu\'il est provisoire', function (string $chemin) {
    $user = personnel('admin');
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)
        ->get($chemin)
        ->assertRedirect(ChangerMotDePasse::getUrl());
})->with(['/admin', '/admin/applications', '/admin/users', '/admin/profile']);

it('laisse atteindre la page de changement elle-même', function () {
    $user = personnel('admin');
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)
        ->get(ChangerMotDePasse::getUrl())
        ->assertOk();
});

it('libère l\'accès une fois le mot de passe changé', function () {
    $user = personnel('admin');
    $user->forceFill(['must_change_password' => true])->save();

    Livewire::actingAs($user)
        ->test(ChangerMotDePasse::class)
        ->fillForm([
            'password' => 'NouveauMotDePasse!2026',
            'password_confirmation' => 'NouveauMotDePasse!2026',
        ])
        ->call('enregistrer')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('NouveauMotDePasse!2026', $user->password))->toBeTrue();

    $this->actingAs($user)->get('/admin')->assertOk();
});

it('n\'importune pas un compte dont le mot de passe est déjà personnel', function () {
    $this->actingAs(personnel('agent'))
        ->get('/admin')
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Historique d'un dossier
|--------------------------------------------------------------------------
*/

it('retient qui a changé le statut, et vers quoi', function () {
    $application = Application::factory()->create(['status' => ApplicationStatus::Nouveau]);
    $agent = personnel('agent');

    $this->actingAs($agent);
    $application->update(['status' => ApplicationStatus::EnCours]);

    $historique = app(ApplicationHistory::class)($application);

    $changement = collect($historique)->firstWhere('action', 'Statut modifié');

    expect($changement)->not->toBeNull()
        ->and($changement['auteur'])->toBe($agent->name)
        ->and($changement['detail'])->toBe(
            ApplicationStatus::Nouveau->label().' → '.ApplicationStatus::EnCours->label(),
        );
});

it('attribue le dépôt au candidat, pas à un membre du cabinet', function () {
    $application = Application::factory()->create();

    $depot = collect(app(ApplicationHistory::class)($application))
        ->firstWhere('action', 'Dossier déposé');

    expect($depot)->not->toBeNull()
        ->and($depot['auteur'])->toBe('Le candidat, depuis le site');
});

it('distingue une modification de notes internes d\'un changement de statut', function () {
    $application = Application::factory()->create(['status' => ApplicationStatus::Nouveau]);
    $admin = personnel('admin');

    $this->actingAs($admin);
    $application->update(['internal_notes' => 'Relancer le candidat.']);

    $actions = collect(app(ApplicationHistory::class)($application))->pluck('action');

    expect($actions)->toContain('Notes internes modifiées')
        ->and($actions)->not->toContain('Statut modifié');
});

it('affiche l\'historique dans la vue détail du dossier', function () {
    $application = Application::factory()->create(['status' => ApplicationStatus::Nouveau]);
    $agent = personnel('agent');

    $this->actingAs($agent);
    $application->update(['status' => ApplicationStatus::Valide]);

    $this->get('/admin/applications/'.$application->reference)
        ->assertOk()
        ->assertSee('Historique du dossier')
        ->assertSee('Statut modifié')
        ->assertSee($agent->name);
});
