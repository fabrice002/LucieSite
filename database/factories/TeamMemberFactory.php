<?php

namespace Database\Factories;

use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'role' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'photo_path' => null,
            'photo_alt' => null,
            'sort_order' => 0,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => true]);
    }
}
