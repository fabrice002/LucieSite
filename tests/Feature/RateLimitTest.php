<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed(SiteContentSeeder::class);
    RateLimiter::clear('suivi');
});

function consulter(object $test, string $reference, string $email = 'candidat@example.cm'): TestResponse
{
    return $test->post(route('suivi.show'), [
        'reference' => $reference,
        'email' => $email,
    ]);
}

/*
|--------------------------------------------------------------------------
| Réseaux partagés : le CGNAT ne doit pas pénaliser les candidats
|--------------------------------------------------------------------------
*/

it('ne bloque pas deux références différentes venues de la même IP', function () {
    // Au Cameroun, une grande part des abonnés mobiles partagent une même IP
    // publique. Deux candidats distincts ne doivent pas se gêner.
    foreach (range(1, 10) as $tentative) {
        consulter($this, 'LN-2026-00001')->assertOk();
    }

    // La onzième sur CETTE référence est bien refusée…
    consulter($this, 'LN-2026-00001')->assertStatus(429);

    // …mais le voisin, sur une autre référence, passe sans encombre.
    consulter($this, 'LN-2026-00002')->assertOk();
});

it('laisse un candidat consulter son dossier dix fois par minute', function () {
    Application::factory()->create([
        'reference' => 'LN-2026-00147',
        'email' => 'candidat@example.cm',
        'status' => ApplicationStatus::EnCours,
    ]);

    foreach (range(1, 10) as $tentative) {
        consulter($this, 'LN-2026-00147')
            ->assertOk()
            ->assertSee(ApplicationStatus::EnCours->label());
    }

    consulter($this, 'LN-2026-00147')->assertStatus(429);
});

it('freine malgré tout une énumération massive depuis une seule IP', function () {
    // Chaque référence a son propre compteur, mais la limite globale par IP
    // finit par arrêter celui qui balaie les références une à une.
    $refuse = false;

    foreach (range(1, 70) as $numero) {
        $reponse = consulter($this, sprintf('LN-2026-%05d', $numero));

        if ($reponse->getStatusCode() === 429) {
            $refuse = true;
            break;
        }
    }

    expect($refuse)->toBeTrue()
        // La limite par IP est de 60 : elle doit se déclencher au-delà.
        ->and($numero)->toBeGreaterThan(10);
});

/*
|--------------------------------------------------------------------------
| Le dépôt garde son plafond strict
|--------------------------------------------------------------------------
*/

it('limite toujours le dépôt à cinq tentatives par minute', function () {
    RateLimiter::clear('depot');

    foreach (range(1, 5) as $tentative) {
        // Charge volontairement invalide : seul le limiteur nous intéresse.
        $this->post(route('depot.store'), [])->assertStatus(302);
    }

    $this->post(route('depot.store'), [])->assertStatus(429);
});

/*
|--------------------------------------------------------------------------
| Proxies de confiance
|--------------------------------------------------------------------------
*/

it('ne fait confiance à aucun proxy par défaut', function () {
    // Sans TRUSTED_PROXIES, un en-tête X-Forwarded-For falsifié ne doit pas
    // permettre de contourner le limiteur en changeant d'IP à volonté.
    $reponse = $this->withHeaders(['X-Forwarded-For' => '203.0.113.42'])
        ->post(route('suivi.show'), [
            'reference' => 'LN-2026-00001',
            'email' => 'candidat@example.cm',
        ]);

    $reponse->assertOk();

    expect(request()->ip())->not->toBe('203.0.113.42');
});
