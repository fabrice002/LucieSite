<?php

namespace App\Filament\Resources\TeamMembers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TeamMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('')
                    ->disk('public')
                    ->circular(),

                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('role')
                    ->label('Fonction')
                    ->searchable(),

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
            ->emptyStateHeading('Aucun membre')
            ->emptyStateDescription('Présentez l\'équipe : c\'est un signal de légitimité déterminant dans ce secteur.');
    }
}
