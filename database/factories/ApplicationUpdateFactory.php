<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationUpdate>
 */
class ApplicationUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'user_id' => User::factory(),
            'status' => ApplicationStatus::EnCours,
            'public_message' => fake()->paragraph(),
            'email_sent' => false,
            'emailed_at' => null,
        ];
    }

    /**
     * Indicate that the update carries a message but no status change.
     */
    public function messageSeul(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => null,
        ]);
    }

    /**
     * Indicate that only the status moved, with nothing to show the candidate.
     */
    public function sansMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'public_message' => null,
        ]);
    }

    /**
     * Indicate that the alert email was queued.
     */
    public function notifiee(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_sent' => true,
            'emailed_at' => now(),
        ]);
    }
}
