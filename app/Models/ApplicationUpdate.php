<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Policies\ApplicationUpdatePolicy;
use Database\Factories\ApplicationUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une mise à jour communiquée au candidat.
 *
 * Le message est lisible sur la page de suivi, après vérification de la
 * référence et de l'adresse e-mail. Il n'est jamais repris dans l'e-mail
 * d'alerte : une boîte e-mail peut être partagée ou compromise.
 *
 * @property int $id
 * @property int $application_id
 * @property int|null $user_id
 * @property ApplicationStatus|null $status
 * @property string|null $public_message
 * @property bool $email_sent
 * @property Carbon|null $emailed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Application $application
 * @property-read User|null $author
 */
#[UsePolicy(ApplicationUpdatePolicy::class)]
#[Fillable([
    'application_id',
    'user_id',
    'status',
    'public_message',
    'email_sent',
    'emailed_at',
])]
class ApplicationUpdate extends Model
{
    /** @use HasFactory<ApplicationUpdateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'email_sent' => 'boolean',
            'emailed_at' => 'datetime',
        ];
    }

    /**
     * Get the application this update belongs to.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the member of staff who wrote the update.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Limit the query to updates the candidate may read.
     *
     * Une mise à jour sans message n'a rien à montrer au candidat : seul le
     * statut a bougé, et il est déjà affiché par ailleurs.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function visibleByApplicant(Builder $query): void
    {
        $query->whereNotNull('public_message')
            ->where('public_message', '!=', '')
            ->latest('created_at')
            ->latest('id');
    }
}
