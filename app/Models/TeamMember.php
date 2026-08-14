<?php

namespace App\Models;

use App\Models\Concerns\CachesPublicContent;
use App\Policies\ContentPolicy;
use Database\Factories\TeamMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Un membre de l'équipe du cabinet.
 *
 * @property int $id
 * @property string $name
 * @property string $role
 * @property string|null $bio
 * @property string|null $photo_path
 * @property string|null $photo_alt
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UsePolicy(ContentPolicy::class)]
#[Fillable(['name', 'role', 'bio', 'photo_path', 'photo_alt', 'sort_order', 'is_published'])]
class TeamMember extends Model
{
    /** @use HasFactory<TeamMemberFactory> */
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
     * @return Collection<int, self>
     */
    public static function publiés(): Collection
    {
        /** @var list<array<string, mixed>> $lignes */
        $lignes = static::rememberPublic(fn (): array => static::query()
            ->published()
            ->get()
            ->map(fn (self $membre): array => $membre->getAttributes())
            ->all());

        return static::hydrate($lignes);
    }

    /**
     * Initiales affichées à défaut de photo.
     */
    public function initials(): string
    {
        return Str::of($this->name)->substr(0, 1)->upper()->value();
    }
}
