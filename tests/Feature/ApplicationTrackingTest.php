<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;

it('affiche le statut quand la référence et l\'e-mail correspondent', function () {
    $application = Application::factory()->create([
        'reference' => 'LN-2026-00147',
        'email' => 'candidat@example.cm',
        'status' => ApplicationStatus::EnCours,
        'internal_notes' => 'Ne doit jamais apparaître publiquement.',
    ]);

    $response = $this->post(route('suivi.show'), [
        'reference' => 'LN-2026-00147',
        'email' => 'candidat@example.cm',
    ]);

    $response->assertOk();
    $response->assertSee(ApplicationStatus::EnCours->label());

    // Ni notes internes, ni documents, ni identité.
    $response->assertDontSee('Ne doit jamais apparaître publiquement.');
    $response->assertDontSee($application->internal_notes);
    $response->assertDontSee($application->phone);
});

it('ne révèle rien pour une référence inconnue', function () {
    $response = $this->post(route('suivi.show'), [
        'reference' => 'LN-2026-99999',
        'email' => 'inconnu@example.cm',
    ]);

    $response->assertOk();
    $response->assertDontSee(ApplicationStatus::Nouveau->label());
    $response->assertSee(content('suivi.introuvable'));
});

it('ne révèle rien quand l\'e-mail ne correspond pas à la référence', function () {
    Application::factory()->create([
        'reference' => 'LN-2026-00147',
        'email' => 'candidat@example.cm',
        'status' => ApplicationStatus::Valide,
    ]);

    $response = $this->post(route('suivi.show'), [
        'reference' => 'LN-2026-00147',
        'email' => 'attaquant@example.cm',
    ]);

    $response->assertOk();
    // Réponse strictement identique à celle d'une référence inconnue.
    $response->assertDontSee(ApplicationStatus::Valide->label());
    $response->assertSee(content('suivi.introuvable'));
});

it('limite les tentatives sur une même référence', function () {
    // 10 par minute et par couple IP + référence : voir RateLimitTest pour les
    // cas de réseaux partagés, qui sont la raison de cette clé composée.
    foreach (range(1, 10) as $attempt) {
        $this->post(route('suivi.show'), [
            'reference' => 'LN-2026-00001',
            'email' => 'brute@example.cm',
        ])->assertOk();
    }

    $this->post(route('suivi.show'), [
        'reference' => 'LN-2026-00001',
        'email' => 'brute@example.cm',
    ])->assertStatus(429);
});
