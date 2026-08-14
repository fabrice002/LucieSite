<?php

namespace App\Models;

use App\Models\Concerns\CachesPublicContent;
use App\Policies\ContentPolicy;
use Database\Factories\FaqCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un thème de questions fréquentes.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Faq> $faqs
 * @property-read int|null $faqs_count
 * @property-read Collection<int, Faq> $publishedFaqs
 * @property-read int|null $published_faqs_count
 */
#[UsePolicy(ContentPolicy::class)]
#[Fillable(['name', 'slug', 'sort_order', 'is_published'])]
class FaqCategory extends Model
{
    /** @use HasFactory<FaqCategoryFactory> */
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
     * @return HasMany<Faq, $this>
     */
    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Les seules questions visibles du public.
     *
     * Relation dédiée plutôt qu'une fermeture de chargement : le typage reste
     * lisible, et la contrainte ne peut pas être oubliée à l'appel.
     *
     * @return HasMany<Faq, $this>
     */
    public function publishedFaqs(): HasMany
    {
        return $this->faqs()->where('is_published', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Les catégories publiées et leurs questions publiées.
     *
     * Une catégorie dépubliée masque ses questions ; une catégorie sans
     * question publiée n'apparaît pas.
     *
     * @return Collection<int, self>
     */
    public static function avecQuestions(): Collection
    {
        /** @var list<array{categorie: array<string, mixed>, questions: list<array<string, mixed>>}> $lignes */
        $lignes = static::rememberPublic(fn (): array => static::query()
            ->published()
            ->with('publishedFaqs')
            ->get()
            ->filter(fn (self $categorie): bool => $categorie->publishedFaqs->isNotEmpty())
            ->map(fn (self $categorie): array => [
                'categorie' => $categorie->getAttributes(),
                'questions' => $categorie->publishedFaqs
                    ->map(fn (Faq $faq): array => $faq->getAttributes())
                    ->all(),
            ])
            ->values()
            ->all());

        // La relation est reconstruite explicitement : hydrate() ne restitue
        // que les attributs, jamais les relations chargées.
        return static::hydrate(array_column($lignes, 'categorie'))
            ->each(function (self $categorie, int $rang) use ($lignes): void {
                $categorie->setRelation('publishedFaqs', Faq::hydrate($lignes[$rang]['questions']));
            });
    }

    /**
     * Le cache des questions et celui des catégories ne font qu'un.
     */
    public static function publicCacheKey(): string
    {
        return 'public:faq';
    }
}
