<?php

use App\Actions\NotifyApplicant;
use App\Enums\ApplicationStatus;
use App\Filament\Widgets\QueueHealthWidget;
use App\Models\Application;
use App\Models\ApplicationUpdate;
use App\Models\User;
use App\Notifications\ApplicationStatusChanged;
use App\Support\QueueHealth;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

function cabinet(string $role = 'agent'): User
{
    Role::findOrCreate($role, 'web');

    return tap(User::factory()->create())->assignRole($role);
}

/*
|--------------------------------------------------------------------------
| 1. L'action informe le candidat
|--------------------------------------------------------------------------
*/

it('change le statut, crée la mise à jour et envoie la notification', function () {
    Notification::fake();

    $application = Application::factory()->create(['status' => ApplicationStatus::Nouveau]);
    $auteur = cabinet();

    $update = app(NotifyApplicant::class)->handle(
        $application,
        ApplicationStatus::EnCours,
        'Votre dossier est en cours d\'étude.',
        true,
        $auteur,
    );

    // Le statut courant reste porté par applications.status.
    expect($application->refresh()->status)->toBe(ApplicationStatus::EnCours);

    expect($update->status)->toBe(ApplicationStatus::EnCours)
        ->and($update->public_message)->toBe('Votre dossier est en cours d\'étude.')
        ->and($update->author->is($auteur))->toBeTrue()
        ->and($update->email_sent)->toBeTrue()
        ->and($update->emailed_at)->not->toBeNull();

    Notification::assertSentOnDemand(
        ApplicationStatusChanged::class,
        fn (ApplicationStatusChanged $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === $application->email,
    );

    // Le candidat ne doit jamais attendre le SMTP.
    expect(new ApplicationStatusChanged($application))->toBeInstanceOf(ShouldQueue::class);
});

/*
|--------------------------------------------------------------------------
| 2. Sans e-mail, rien ne part
|--------------------------------------------------------------------------
*/

it('enregistre la mise à jour sans rien envoyer quand l\'alerte est désactivée', function () {
    Notification::fake();

    $application = Application::factory()->create(['status' => ApplicationStatus::Nouveau]);

    $update = app(NotifyApplicant::class)->handle(
        $application,
        ApplicationStatus::Incomplet,
        'Il manque votre relevé de notes.',
        false,
        cabinet(),
    );

    expect($application->refresh()->status)->toBe(ApplicationStatus::Incomplet)
        ->and($update->email_sent)->toBeFalse()
        ->and($update->emailed_at)->toBeNull();

    Notification::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| 3. L'e-mail ne transporte jamais le message
|--------------------------------------------------------------------------
*/

it('n\'inclut jamais le message du cabinet dans l\'e-mail', function () {
    $this->seed(SiteContentSeeder::class);

    $secret = 'Votre visa a été refusé pour motif de fraude documentaire.';

    $application = Application::factory()->create([
        'reference' => 'LN-2026-00147',
        'status' => ApplicationStatus::Rejete,
        'internal_notes' => 'Dossier suspect, à ne surtout pas divulguer.',
    ]);

    app(NotifyApplicant::class)->handle($application, ApplicationStatus::Rejete, $secret, false, cabinet());

    $mail = (new ApplicationStatusChanged($application->refresh()))->toMail($application);
    $rendu = (string) $mail->render();

    expect($rendu)->not->toContain($secret)
        ->and($rendu)->not->toContain('Dossier suspect')
        // Il alerte, sans plus : référence, statut, invitation à se connecter.
        ->and($rendu)->toContain('LN-2026-00147')
        ->and($rendu)->toContain(ApplicationStatus::Rejete->label())
        ->and($rendu)->toContain(route('suivi.index'));

    // Ni jeton, ni connexion automatique, ni pièce jointe.
    expect($mail->actionUrl)->toBe(route('suivi.index'))
        ->and($mail->attachments)->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| 4. La page de suivi montre les messages, et rien de plus
|--------------------------------------------------------------------------
*/

it('affiche les messages publics sur la page de suivi, jamais les notes internes', function () {
    $this->seed(SiteContentSeeder::class);

    $application = Application::factory()->create([
        'reference' => 'LN-2026-00147',
        'email' => 'candidat@example.cm',
        'status' => ApplicationStatus::EnCours,
        'internal_notes' => 'Relancer par téléphone, candidat peu réactif.',
    ]);

    $auteur = cabinet();
    $notifier = app(NotifyApplicant::class);

    $notifier->handle($application, ApplicationStatus::EnCours, 'Votre dossier est en cours d\'étude.', false, $auteur);
    // Un changement de statut sans message ne doit rien afficher au candidat.
    $notifier->handle($application, ApplicationStatus::Valide, null, false, $auteur);

    $response = $this->post(route('suivi.show'), [
        'reference' => 'LN-2026-00147',
        'email' => 'candidat@example.cm',
    ]);

    $response->assertOk()
        ->assertSee('Messages du cabinet')
        ->assertSee('Votre dossier est en cours d\'étude.')
        ->assertSee(ApplicationStatus::Valide->label());

    // Rien de ce qui est privé ne doit filtrer.
    $response->assertDontSee('Relancer par téléphone')
        ->assertDontSee($auteur->name)
        ->assertDontSee($application->phone);
});

/*
|--------------------------------------------------------------------------
| 5. Une mise à jour vide est refusée
|--------------------------------------------------------------------------
*/

it('refuse une mise à jour sans statut et sans message', function () {
    $application = Application::factory()->create();

    expect(fn () => app(NotifyApplicant::class)->handle($application, null, null, false, cabinet()))
        ->toThrow(InvalidArgumentException::class);

    expect(ApplicationUpdate::count())->toBe(0);
})->with([
    'rien du tout' => null,
    'des espaces seulement' => '   ',
]);

/*
|--------------------------------------------------------------------------
| Compléments
|--------------------------------------------------------------------------
*/

it('accepte un message seul, sans changement de statut', function () {
    Notification::fake();

    $application = Application::factory()->create(['status' => ApplicationStatus::EnCours]);

    $update = app(NotifyApplicant::class)->handle(
        $application,
        null,
        'Nous avons bien reçu votre relevé de notes.',
        true,
        cabinet(),
    );

    expect($update->status)->toBeNull()
        // Le statut du dossier n'a pas bougé.
        ->and($application->refresh()->status)->toBe(ApplicationStatus::EnCours);

    Notification::assertSentOnDemand(ApplicationStatusChanged::class);
});

it('journalise l\'événement avec son auteur', function () {
    Notification::fake();

    $application = Application::factory()->create(['status' => ApplicationStatus::Nouveau]);
    $auteur = cabinet('admin');

    app(NotifyApplicant::class)->handle($application, ApplicationStatus::Valide, 'Dossier validé.', false, $auteur);

    $journal = Activity::query()
        ->where('subject_type', $application->getMorphClass())
        ->where('subject_id', $application->getKey())
        ->where('description', 'Candidat informé')
        ->sole();

    expect($journal->causer_id)->toBe($auteur->getKey())
        // Le journal note qu'un message existe, jamais son contenu.
        ->and($journal->properties['message'])->toBeTrue()
        ->and(json_encode($journal->properties))->not->toContain('Dossier validé.');
});

/*
|--------------------------------------------------------------------------
| Garde-fou sur la file d'attente
|--------------------------------------------------------------------------
*/

it('signale une file d\'attente à l\'arrêt', function () {
    config(['queue.default' => 'database']);

    $sante = app(QueueHealth::class);

    expect($sante->estBloquee())->toBeFalse();

    // Un job qui attend depuis un quart d'heure : le worker ne tourne plus.
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'available_at' => now()->subMinutes(15)->getTimestamp(),
        'created_at' => now()->subMinutes(15)->getTimestamp(),
    ]);

    expect($sante->estBloquee())->toBeTrue()
        ->and($sante->enAttente())->toBe(1);
});

it('ne s\'alarme pas d\'un job qui vient d\'être mis en file', function () {
    config(['queue.default' => 'database']);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'available_at' => now()->getTimestamp(),
        'created_at' => now()->getTimestamp(),
    ]);

    expect(app(QueueHealth::class)->estBloquee())->toBeFalse();
});

/**
 * Insère un job qui traîne depuis un quart d'heure.
 */
function fileBloquee(): void
{
    config(['queue.default' => 'database']);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'available_at' => now()->subMinutes(15)->getTimestamp(),
        'created_at' => now()->subMinutes(15)->getTimestamp(),
    ]);
}

it('affiche le bandeau d\'alerte à l\'administrateur', function () {
    fileBloquee();

    Livewire::actingAs(cabinet('admin'))
        ->test(QueueHealthWidget::class)
        ->assertSee('Les e-mails ne partent pas')
        ->assertSee('queue:work');
});

it('n\'affiche le bandeau ni à l\'agent, ni quand la file avance', function () {
    fileBloquee();

    // L'agent ne peut pas relancer le worker : l'alerte ne le concerne pas.
    Auth::login(cabinet('agent'));
    expect(QueueHealthWidget::canView())->toBeFalse();

    // File saine : aucune alerte, même pour un administrateur.
    DB::table('jobs')->delete();
    Auth::login(cabinet('admin'));
    expect(QueueHealthWidget::canView())->toBeFalse();
});
