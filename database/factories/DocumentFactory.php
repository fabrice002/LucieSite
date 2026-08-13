<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(DocumentType::cases());

        return [
            'application_id' => Application::factory(),
            'type' => $type,
            'original_name' => $type->value.'-'.fake()->word().'.pdf',
            // Le nom stocké est un UUID : le nom d'origine ne sert qu'à l'affichage.
            'path' => 'documents/'.Str::uuid()->toString().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(50_000, 10_485_760),
        ];
    }

    /**
     * Indicate the type of the document.
     */
    public function type(DocumentType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'original_name' => $type->value.'-'.fake()->word().'.pdf',
        ]);
    }

    /**
     * Indicate that the document is a photographed scan rather than a PDF.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_name' => Str::replaceLast('.pdf', '.jpg', (string) $attributes['original_name']),
            'path' => 'documents/'.Str::uuid()->toString().'.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }
}
