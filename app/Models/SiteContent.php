<?php

namespace App\Models;

use App\Observers\SiteContentObserver;
use App\Policies\SiteContentPolicy;
use Database\Factories\SiteContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Bloc de textes d'une page publique, éditable depuis le back-office.
 *
 * @property int $id
 * @property string $key
 * @property string $locale
 * @property string $label
 * @property array<string, string> $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ObservedBy(SiteContentObserver::class)]
#[UsePolicy(SiteContentPolicy::class)]
#[Fillable([
    'key',
    'locale',
    'label',
    'content',
])]
class SiteContent extends Model
{
    /** @use HasFactory<SiteContentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}
