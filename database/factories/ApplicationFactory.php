<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => sprintf('LN-%d-%05d', now()->year, fake()->unique()->numberBetween(1, 99999)),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2376'.fake()->numerify('########'),
            'country_of_residence' => 'Cameroun',
            'target_program' => fake()->randomElement(['Entrée Express', 'PSTQ', 'Permis d\'études', null]),
            'message' => fake()->optional()->paragraph(),
            'status' => ApplicationStatus::Nouveau,
            'internal_notes' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

    /**
     * Indicate the status of the application.
     */
    public function status(ApplicationStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Indicate that the application has been soft deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now()->subDays(120),
        ]);
    }

    /**
     * Un dossier dont l'échéance de conservation est déjà dépassée.
     *
     * L'observateur repousse l'échéance à chaque écriture : elle est donc
     * repositionnée après création, sans toucher aux timestamps.
     */
    public function echu(): static
    {
        return $this->afterCreating(function (Application $application): void {
            $application->timestamps = false;
            $application->retention_due_at = now()->subDay();
            $application->save();
        });
    }

    /**
     * Un dossier qui arrivera à échéance dans le nombre de jours indiqué.
     */
    public function echeanceDans(int $jours): static
    {
        return $this->afterCreating(function (Application $application) use ($jours): void {
            $application->timestamps = false;
            $application->retention_due_at = now()->addDays($jours);
            $application->save();
        });
    }
}
