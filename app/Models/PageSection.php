<?php

namespace App\Models;

use App\Enums\SectionType;
use App\Models\Concerns\CachesPublicContent;
use App\Policies\ContentPolicy;
use Database\Factories\PageSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un bloc empilable, composant une page publique.
 *
 * La cliente construit ses pages en empilant ces blocs depuis le back-office :
 * elle en ajoute autant qu'elle veut, les réordonne, et publie ou dépublie
 * chacun sans intervention technique.
 *
 * @property int $id
 * @property string $page
 * @property string $type
 * @property int $sort_order
 * @property bool $is_published
 * @property array<string, mixed> $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UsePolicy(ContentPolicy::class)]
#[Fillable(['page', 'type', 'sort_order', 'is_published', 'data'])]
class PageSection extends Model
{
    /** @use HasFactory<PageSectionFactory> */
    use CachesPublicContent, HasFactory;

    /**
     * Les pages qui acceptent des blocs.
     *
     * @return array<string, string>
     */
    public static function pages(): array
    {
        return [
            'accueil' => 'Page d\'accueil',
            'services' => 'Page Services',
            'a-propos' => 'Page À propos',
            'contact' => 'Page Contact',
        ];
    }

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
            'data' => 'array',
        ];
    }

    /**
     * Le type du bloc, ou null si le code ne le connaît plus.
     *
     * Volontairement non casté en enum : un cast ferait lever une exception à
     * la simple lecture de l'attribut si un type venait à disparaître du code.
     * Le cahier des charges demande l'inverse — un type inconnu doit être
     * ignoré, jamais casser la page.
     */
    public function sectionType(): ?SectionType
    {
        return SectionType::tryFrom((string) $this->type);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Les blocs publiés d'une page, dans l'ordre, mis en cache.
     *
     * @return Collection<int, self>
     */
    public static function pour(string $page): Collection
    {
        /** @var array<string, list<array<string, mixed>>> $parPage */
        $parPage = static::rememberPublic(fn (): array => static::query()
            ->published()
            ->get()
            ->groupBy('page')
            ->map(fn (Collection $blocs): array => $blocs
                ->map(fn (self $bloc): array => $bloc->getAttributes())
                ->all())
            ->all());

        return static::hydrate($parPage[$page] ?? []);
    }

    /**
     * Lit une valeur du bloc, avec repli.
     */
    public function valeur(string $cle, mixed $defaut = null): mixed
    {
        return data_get($this->data, $cle, $defaut);
    }

    /**
     * Lit une liste du bloc — cartes, étapes, images…
     *
     * @return list<array<string, mixed>>
     */
    public function liste(string $cle): array
    {
        $valeur = data_get($this->data, $cle, []);

        /** @var list<array<string, mixed>> */
        return is_array($valeur) ? array_values(array_filter($valeur, is_array(...))) : [];
    }
}
