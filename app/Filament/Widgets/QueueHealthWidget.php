<?php

namespace App\Filament\Widgets;

use App\Support\QueueHealth;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Bandeau d'alerte lorsque les e-mails ne partent plus.
 *
 * Réservé à `admin` : c'est la personne en mesure de relancer le worker.
 */
class QueueHealthWidget extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.queue-health';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole('admin')
            && app(QueueHealth::class)->estBloquee();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $sante = app(QueueHealth::class);

        return [
            'depuis' => $sante->attenteDepuis(),
            'enAttente' => $sante->enAttente(),
        ];
    }
}
