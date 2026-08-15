<?php

namespace App\Filament\Widgets;

use App\Actions\PurgeExpiredApplications;
use App\Filament\Pages\DossiersEnAttente;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Bandeau du tableau de bord : des dossiers attendent une décision.
 *
 * Volontairement non masquable et sans bouton de fermeture. Le prix du choix de
 * ne rien supprimer automatiquement, c'est que quelqu'un doit trancher ; si le
 * rappel pouvait être écarté d'un clic, des passeports resteraient stockés
 * indéfiniment et la règle de conservation ne vaudrait plus rien.
 */
class PendingRetentionBanner extends Widget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.pending-retention-banner';

    /**
     * Le bandeau disparaît de lui-même dès que la file est vide — et seulement
     * à ce moment-là.
     */
    public static function canView(): bool
    {
        if (! Auth::user()?->hasRole('admin')) {
            return false;
        }

        return PurgeExpiredApplications::enAttenteDeDecision()->exists();
    }

    public function nombre(): int
    {
        return PurgeExpiredApplications::enAttenteDeDecision()->count();
    }

    public function url(): string
    {
        return DossiersEnAttente::getUrl();
    }
}
