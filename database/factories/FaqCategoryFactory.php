<?php

namespace Database\Factories;

use App\Models\FaqCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FaqCategory>
 */
class FaqCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nom = implode(' ', (array) fake()->unique()->words(2));

        return [
            'name' => ucfirst($nom),
            'slug' => Str::slug($nom),
            'sort_order' => 0,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
