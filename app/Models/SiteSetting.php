<?php

namespace App\Models;

use App\Observers\SiteSettingObserver;
use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Un réglage d'apparence, modifiable depuis le back-office.
 *
 * Une ligne par réglage plutôt qu'une colonne : ajouter une couleur ou une
 * option n'impose alors aucune migration.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ObservedBy(SiteSettingObserver::class)]
#[Fillable(['key', 'value', 'type'])]
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    /**
     * Les natures de réglage, qui commandent la conversion à la lecture.
     */
    public const TYPE_COULEUR = 'couleur';

    public const TYPE_TEXTE = 'texte';

    public const TYPE_BOOLEEN = 'booleen';

    public const TYPE_IMAGE = 'image';
}
