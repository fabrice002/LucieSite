<?php

namespace App\Filament\Resources\FaqCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FaqCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Thème')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('faqs_count')
                    ->label('Questions')
                    ->counts('faqs')
                    ->badge(),

                TextColumn::make('published_faqs_count')
                    ->label('Publiées')
                    ->counts('publishedFaqs')
                    ->badge()
                    ->color('success'),

                ToggleColumn::make('is_published')
                    ->label('Publié'),
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
            ->emptyStateHeading('Aucun thème de questions')
            ->emptyStateDescription('Regroupez les questions par thème : le dépôt, les délais, les documents…');
    }
}
