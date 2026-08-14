<?php

namespace App\Models;

use App\Models\Concerns\CachesPublicContent;
use App\Policies\ContentPolicy;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un programme d'immigration présenté par le cabinet.
 *
 * Chaque service a sa propre page, référencée séparément : c'est le schéma
 * retenu par les cabinets qui traitent chaque programme à part, et ce qui
 * apporte le trafic organique.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $summary
 * @property string|null $body
 * @property string|null $image_path
 * @property string|null $image_alt
 * @property string|null $icon
 * @property string|null $highlight
 * @property int $sort_order
 * @property bool $is_published
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[RouteKey('slug')]
#[UsePolicy(ContentPolicy::class)]
#[Fillable([
    'slug',
    'title',
    'summary',
    'body',
    'image_path',
    'image_alt',
    'icon',
    'highlight',
    'sort_order',
    'is_published',
    'meta_title',
    'meta_description',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
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
     * Limit the query to published services, in display order.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    /**
     * Tous les services publiés, mis en cache.
     *
     * @return Collection<int, self>
     */
    public static function publiés(): Collection
    {
        // query() est indispensable : depuis l'intérieur de la classe,
        // static::published() appellerait la méthode de scope elle-même au lieu
        // de passer par le constructeur de requête.
        /** @var list<array<string, mixed>> $lignes */
        $lignes = static::rememberPublic(fn (): array => static::query()
            ->published()
            ->get()
            ->map(fn (self $service): array => $service->getAttributes())
            ->all());

        return static::hydrate($lignes);
    }
}
