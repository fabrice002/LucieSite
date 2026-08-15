<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titre = fake()->unique()->sentence(3);

        return [
            'slug' => Str::slug($titre),
            'title' => $titre,
            'summary' => fake()->sentence(15),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'price_note' => null,
            'included' => null,
            'excluded' => null,
            'image_path' => null,
            'image_alt' => null,
            'icon' => null,
            'highlight' => null,
            'sort_order' => 0,
            'is_published' => false,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => true]);
    }

    /**
     * Un service dont le périmètre est renseigné, comme il devrait l'être.
     */
    public function avecPerimetre(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_note' => 'Sur devis, après évaluation du profil',
            'included' => [
                'Évaluation complète de votre profil',
                'Constitution et vérification du dossier',
                'Suivi jusqu\'à la décision',
            ],
            'excluded' => [
                'Frais gouvernementaux et frais de visa',
                'Traductions certifiées',
                'Examens de langue (TCF, TEF, IELTS)',
            ],
        ]);
    }
}
