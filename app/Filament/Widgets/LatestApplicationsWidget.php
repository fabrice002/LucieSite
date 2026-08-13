<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestApplicationsWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Cinq derniers dossiers reçus';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                /** @var Builder<Application> */
                Application::query()->latest('created_at')->limit(5),
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->weight('medium'),

                TextColumn::make('full_name')->label('Nom complet'),

                TextColumn::make('country_of_residence')->label('Pays'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                    ->color(fn (ApplicationStatus $state): string => $state->color()),

                TextColumn::make('created_at')
                    ->label('Déposé le')
                    ->since(),
            ])
            ->recordActions([
                Action::make('consulter')
                    ->label('Consulter')
                    ->url(fn (Application $record): string => ApplicationResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Aucun dossier pour le moment');
    }
}
