<?php

namespace App\Filament\Resources\SiteContents\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SiteContentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('label')
            ->columns([
                TextColumn::make('label')
                    ->label('Bloc de textes')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('key')
                    ->label('Clé technique')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('content')
                    ->label('Textes')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state)
                        ? count($state).' texte'.(count($state) > 1 ? 's' : '')
                        : '—')
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('j M Y à H:i')
                    ->sortable(),
            ])
            // Ni création ni suppression : les clés sont référencées dans les vues.
            ->recordActions([
                EditAction::make()->label('Modifier les textes'),
            ])
            ->toolbarActions([])
            ->paginated(false)
            ->emptyStateHeading('Aucun bloc de textes')
            ->emptyStateDescription('Lancez « php artisan db:seed --class=SiteContentSeeder » pour les créer.');
    }
}
