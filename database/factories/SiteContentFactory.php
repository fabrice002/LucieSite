<?php

namespace Database\Factories;

use App\Models\SiteContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteContent>
 */
class SiteContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->slug(1);

        return [
            'key' => $key,
            'locale' => 'fr',
            'label' => ucfirst($key),
            'content' => [
                'titre' => fake()->sentence(),
                'sous_titre' => fake()->sentence(),
            ],
        ];
    }

    /**
     * Set the key and label of the block.
     */
    public function key(string $key, ?string $label = null): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
            'label' => $label ?? ucfirst($key),
        ]);
    }

    /**
     * Set the translatable payload of the block.
     *
     * @param  array<string, string>  $content
     */
    public function content(array $content): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $content,
        ]);
    }
}
