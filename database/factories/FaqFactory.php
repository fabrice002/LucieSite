<?php

namespace Database\Factories;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'faq_category_id' => FaqCategory::factory(),
            'question' => rtrim(fake()->sentence(8), '.').' ?',
            'answer' => '<p>'.fake()->paragraph().'</p>',
            'sort_order' => 0,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
