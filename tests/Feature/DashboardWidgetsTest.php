<?php

use App\Enums\ApplicationStatus;
use App\Filament\Widgets\ApplicationStatsWidget;
use App\Filament\Widgets\LatestApplicationsWidget;
use App\Models\Application;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function agent(): User
{
    Role::findOrCreate('agent', 'web');

    return tap(User::factory()->create())->assignRole('agent');
}

it('compte les dossiers par statut et ceux du mois', function () {
    Application::factory()->count(3)->create(['status' => ApplicationStatus::Nouveau]);
    Application::factory()->count(2)->create(['status' => ApplicationStatus::Valide]);
    Application::factory()->create([
        'status' => ApplicationStatus::Valide,
        'created_at' => now()->subMonths(3),
    ]);

    Livewire::actingAs(agent())
        ->test(ApplicationStatsWidget::class)
        ->assertSee('Dossiers reçus ce mois')
        // 5 dossiers ce mois-ci, le sixième date de trois mois.
        ->assertSee('5')
        ->assertSee(ApplicationStatus::Nouveau->label())
        ->assertSee(ApplicationStatus::Valide->label());
});

it('affiche les cinq derniers dossiers reçus', function () {
    Application::factory()->count(7)->sequence(
        fn ($sequence) => [
            'reference' => sprintf('LN-2026-%05d', $sequence->index + 1),
            'created_at' => now()->subDays(10 - $sequence->index),
        ],
    )->create();

    $widget = Livewire::actingAs(agent())->test(LatestApplicationsWidget::class);

    // Les cinq plus récents, pas les deux premiers.
    $widget->assertSee('LN-2026-00007')
        ->assertSee('LN-2026-00003')
        ->assertDontSee('LN-2026-00002')
        ->assertDontSee('LN-2026-00001');
});
