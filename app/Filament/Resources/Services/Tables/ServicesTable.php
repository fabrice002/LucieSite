<?php

namespace App\Filament\Resources\Services\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Réordonnancement par glisser-déposer : la cliente compose l'ordre
            // d'affichage sans toucher à un champ numérique.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk('public')
                    ->imageWidth(64)
                    ->imageHeight(40)
                    ->defaultImageUrl(null),

                TextColumn::make('title')
                    ->label('Service')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn ($record): string => $record->slug),

                TextColumn::make('highlight')
                    ->label('Mention')
                    ->badge()
                    ->placeholder('—'),

                ToggleColumn::make('is_published')
                    ->label('Publié'),

                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('j M Y')
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label('Publication'),
            ])
            ->recordActions([
                EditAction::make()->label('Modifier'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun service')
            ->emptyStateDescription('Créez un service par programme d\'immigration : chacun aura sa propre page.');
    }
}
