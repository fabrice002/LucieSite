<?php

namespace Database\Factories;

use App\Enums\SectionType;
use App\Models\PageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageSection>
 */
class PageSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page' => 'accueil',
            'type' => SectionType::Cta->value,
            'sort_order' => 0,
            'is_published' => true,
            'data' => [
                'titre' => fake()->sentence(5),
                'texte' => fake()->sentence(12),
                'bouton_libelle' => 'Déposer mon dossier',
                'bouton_url' => '/deposer-mon-dossier',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function type(SectionType $type, array $data = []): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type->value,
            'data' => $data !== [] ? $data : $attributes['data'],
        ]);
    }

    public function sur(string $page): static
    {
        return $this->state(fn (array $attributes) => ['page' => $page]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
