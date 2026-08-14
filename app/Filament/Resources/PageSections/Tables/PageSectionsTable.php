<?php

namespace App\Filament\Resources\PageSections\Tables;

use App\Enums\SectionType;
use App\Models\PageSection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class PageSectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Glisser-déposer pour composer l'ordre des blocs sur la page.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->defaultGroup('page')
            ->groups([
                Group::make('page')
                    ->label('Page')
                    ->getTitleFromRecordUsing(fn (PageSection $record): string => PageSection::pages()[$record->page] ?? $record->page),
            ])
            ->columns([
                TextColumn::make('type')
                    ->label('Type de bloc')
                    ->badge()
                    // tryFrom, et non from : un bloc dont le type n'existe plus
                    // reste consultable et supprimable depuis le back-office.
                    ->icon(fn (string $state): ?string => SectionType::tryFrom($state)?->icon())
                    ->formatStateUsing(fn (string $state): string => SectionType::tryFrom($state)?->label() ?? $state),

                TextColumn::make('data.titre')
                    ->label('Titre')
                    ->placeholder('—')
                    ->limit(60),

                ToggleColumn::make('is_published')
                    ->label('Publié'),

                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('j M Y')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('page')
                    ->label('Page')
                    ->options(PageSection::pages()),

                SelectFilter::make('type')
                    ->label('Type')
                    ->options(SectionType::options()),

                TernaryFilter::make('is_published')->label('Publication'),
            ])
            ->recordActions([
                EditAction::make()->label('Modifier'),
                ReplicateAction::make()->label('Dupliquer'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun bloc')
            ->emptyStateDescription('Empilez des blocs pour composer vos pages : bandeau, cartes, étapes, citation…');
    }
}
