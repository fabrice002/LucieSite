<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\RetentionState;
// Les dates de conservation sont écrites depuis le code (now()->addMonths(…)),
// et AppServiceProvider fixe CarbonImmutable comme classe de date.
use App\Observers\ApplicationRetentionObserver;
use App\Policies\ApplicationPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $reference
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $country_of_residence
 * @property string|null $target_program
 * @property string|null $message
 * @property ApplicationStatus $status
 * @property string|null $internal_notes
 * @property string|null $ip_address
 * @property Carbon|null $consented_at
 * @property string|null $privacy_version
 * @property RetentionState|null $retention_state
 * @property CarbonImmutable|null $retention_due_at
 * @property CarbonImmutable|null $retention_reminded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read Collection<int, Document> $documents
 * @property-read int|null $documents_count
 * @property-read Collection<int, ApplicationUpdate> $updates
 * @property-read int|null $updates_count
 */
// L'identifiant numérique n'est jamais exposé : on route sur la référence.
#[RouteKey('reference')]
#[UsePolicy(ApplicationPolicy::class)]
#[Fillable([
    'reference',
    'first_name',
    'last_name',
    'email',
    'phone',
    'country_of_residence',
    'target_program',
    'message',
    'status',
    'internal_notes',
    'ip_address',
    'consented_at',
    'privacy_version',
    'retention_state',
    'retention_due_at',
    'retention_reminded_at',
])]
#[ObservedBy(ApplicationRetentionObserver::class)]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Journalise le dépôt, les changements de statut et les suppressions,
     * avec l'utilisateur auteur quand il y en a un.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('dossier')
            ->logOnly(['status', 'internal_notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => match ($event) {
                'created' => 'Dossier déposé',
                'updated' => 'Dossier modifié',
                'deleted' => 'Dossier supprimé',
                'restored' => 'Dossier restauré',
                default => $event,
            });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'consented_at' => 'datetime',
            'retention_state' => RetentionState::class,
            'retention_due_at' => 'datetime',
            'retention_reminded_at' => 'datetime',
        ];
    }

    /**
     * Les colonnes qui ne décrivent pas le dossier, mais sa conservation.
     *
     * Les modifier ne doit pas compter comme une activité : sans quoi envoyer
     * un rappel repousserait l'échéance qu'il annonce.
     *
     * @var list<string>
     */
    public const COLONNES_DE_CONSERVATION = [
        'retention_state',
        'retention_due_at',
        'retention_reminded_at',
    ];

    /**
     * Le dossier attend-il une décision de conservation ?
     */
    public function attendUneDecision(): bool
    {
        return $this->retention_state === RetentionState::EnAttenteDeDecision;
    }

    /**
     * Repousse l'échéance de conservation, sans enregistrer.
     *
     * Un seul endroit calcule cette date : l'observateur du dossier l'appelle
     * sur toute activité, et celui des messages au candidat également. Le
     * dossier quitte du même coup la file d'attente, et les rappels repartent
     * de zéro.
     */
    public function repousserEcheance(): void
    {
        $this->retention_due_at = now()->addMonths((int) config('retention.months', 36));
        $this->retention_state = null;
        $this->retention_reminded_at = null;
    }

    /**
     * Date de la dernière activité connue sur le dossier.
     *
     * Sert à la conservation : un dossier suivi, même ancien, n'est jamais
     * purgé. Seuls les dossiers réellement abandonnés le sont.
     */
    public function lastActivityAt(): Carbon
    {
        $dates = [
            $this->updated_at,
            $this->created_at,
            $this->updates()->max('created_at'),
        ];

        $plusRecente = collect($dates)
            ->filter()
            ->map(fn (mixed $date): Carbon => Carbon::parse((string) $date))
            ->max();

        return $plusRecente ?? Carbon::now();
    }

    /**
     * Get the documents attached to this application.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the updates communicated to the candidate, most recent first.
     *
     * @return HasMany<ApplicationUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(ApplicationUpdate::class)
            ->latest('created_at')
            ->latest('id');
    }

    /**
     * Get the candidate's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
