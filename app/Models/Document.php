<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Policies\DocumentPolicy;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $application_id
 * @property DocumentType $type
 * @property string $original_name
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Application $application
 */
#[UsePolicy(DocumentPolicy::class)]
#[Fillable([
    'application_id',
    'type',
    'original_name',
    'path',
    'mime_type',
    'size',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'size' => 'integer',
        ];
    }

    /**
     * Get the application this document belongs to.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the file name to hand back on download.
     *
     * Le nom d'origine vient du navigateur du candidat et peut arriver sans
     * extension. Sans elle, le fichier téléchargé n'est associé à aucune
     * application et l'administratrice doit la rajouter à la main. On complète
     * donc à partir de l'extension du fichier réellement stocké.
     */
    public function downloadName(): string
    {
        $name = trim($this->original_name);

        if ($name === '') {
            $name = $this->type->value;
        }

        if (pathinfo($name, PATHINFO_EXTENSION) !== '') {
            return $name;
        }

        $extension = pathinfo($this->path, PATHINFO_EXTENSION);

        return $extension !== '' ? $name.'.'.$extension : $name;
    }
}
