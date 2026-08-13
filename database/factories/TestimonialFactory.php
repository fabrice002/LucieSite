<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_name' => fake()->name(),
            'author_country' => fake()->randomElement(['Cameroun', 'Canada', 'Côte d\'Ivoire', null]),
            'content' => fake()->paragraph(),
            'photo_path' => null,
            'video_url' => null,
            'is_published' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the testimonial is visible on the public site.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }
}
