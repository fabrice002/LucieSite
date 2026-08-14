<?php

namespace App\Models;

use App\Models\Concerns\CachesPublicContent;
use App\Policies\ContentPolicy;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une question fréquente.
 *
 * @property int $id
 * @property int $faq_category_id
 * @property string $question
 * @property string $answer
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FaqCategory $category
 */
#[UsePolicy(ContentPolicy::class)]
#[Fillable(['faq_category_id', 'question', 'answer', 'sort_order', 'is_published'])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use CachesPublicContent, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FaqCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }

    /**
     * Une question et sa catégorie partagent le même cache.
     */
    public static function publicCacheKey(): string
    {
        return 'public:faq';
    }

    /**
     * L'ancre permettant de partager le lien direct vers cette question.
     */
    public function anchor(): string
    {
        return 'faq-'.$this->getKey();
    }
}
