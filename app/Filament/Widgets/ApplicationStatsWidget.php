<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ApplicationStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $parStatut = Application::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $ceMois = Application::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $stats = [
            Stat::make('Dossiers reçus ce mois', (string) $ceMois)
                ->description(now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),
        ];

        foreach (ApplicationStatus::cases() as $statut) {
            $stats[] = Stat::make(
                $statut->label(),
                (string) ($parStatut[$statut->value] ?? 0),
            )->color($statut->color());
        }

        return $stats;
    }
}
