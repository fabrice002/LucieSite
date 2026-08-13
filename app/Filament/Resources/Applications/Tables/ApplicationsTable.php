<?php

namespace App\Filament\Resources\Applications\Tables;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Référence')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Référence copiée')
                    ->weight('medium'),

                TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name']),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('country_of_residence')
                    ->label('Pays')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                    ->color(fn (ApplicationStatus $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('documents_count')
                    ->label('Documents')
                    ->counts('documents')
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Déposé le')
                    ->dateTime('j M Y à H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(ApplicationStatus::options())
                    ->multiple(),

                SelectFilter::make('country_of_residence')
                    ->label('Pays de résidence')
                    ->options(fn (): array => Application::query()
                        ->distinct()
                        ->orderBy('country_of_residence')
                        ->pluck('country_of_residence', 'country_of_residence')
                        ->all())
                    ->multiple(),

                Filter::make('periode')
                    ->label('Période de dépôt')
                    ->schema([
                        DatePicker::make('depose_du')
                            ->label('Déposé du')
                            ->native(false),
                        DatePicker::make('depose_au')
                            ->label("Déposé jusqu'au")
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['depose_du'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))
                        ->when($data['depose_au'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date))),

                TrashedFilter::make()->label('Dossiers supprimés'),
            ])
            ->recordActions([
                ViewAction::make()->label('Consulter'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('changer_statut')
                        ->label('Changer le statut')
                        ->icon('heroicon-o-arrow-path')
                        ->schema([
                            Select::make('status')
                                ->label('Nouveau statut')
                                ->options(ApplicationStatus::options())
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $statut = ApplicationStatus::from($data['status']);

                            DB::transaction(function () use ($records, $statut): void {
                                foreach ($records as $record) {
                                    $record->update(['status' => $statut]);
                                }
                            });

                            Notification::make()
                                ->success()
                                ->title($records->count().' dossier(s) mis à jour')
                                ->body('Nouveau statut : '.$statut->label())
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['admin', 'agent']) ?? false),

                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun dossier')
            ->emptyStateDescription('Les dossiers déposés depuis le site apparaîtront ici.');
    }
}
