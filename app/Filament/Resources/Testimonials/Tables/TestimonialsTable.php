<?php

namespace App\Filament\Resources\Testimonials\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Le glisser-déposer écrit directement dans sort_order.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Photo')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(null),

                TextColumn::make('author_name')
                    ->label('Auteur')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('author_country')
                    ->label('Pays')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('content')
                    ->label('Extrait')
                    ->limit(70)
                    ->wrap(),

                ToggleColumn::make('is_published')
                    ->label('Publié'),

                TextColumn::make('sort_order')
                    ->label('Ordre')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Publication')
                    ->placeholder('Tous')
                    ->trueLabel('Publiés uniquement')
                    ->falseLabel('Brouillons uniquement'),
            ])
            ->recordActions([
                EditAction::make()->label('Modifier'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun témoignage')
            ->emptyStateDescription('Les témoignages que vous ajoutez ici alimentent la page publique.');
    }
}
