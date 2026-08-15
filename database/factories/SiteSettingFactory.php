<?php

namespace Database\Factories;

use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSetting>
 */
class SiteSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'type' => SiteSetting::TYPE_TEXTE,
        ];
    }

    public function couleur(string $key, string $valeur): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
            'value' => $valeur,
            'type' => SiteSetting::TYPE_COULEUR,
        ]);
    }

    public function booleen(string $key, bool $valeur): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
            'value' => $valeur ? '1' : '0',
            'type' => SiteSetting::TYPE_BOOLEEN,
        ]);
    }
}
