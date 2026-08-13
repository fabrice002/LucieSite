<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (User $record): ?string => $record->is(Auth::user()) ? 'Vous' : null),

                TextColumn::make('email')
                    ->label('Adresse e-mail')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('roles.name')
                    ->label('Rôle')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'admin' ? 'danger' : 'info')
                    ->placeholder('Aucun — accès refusé'),

                IconColumn::make('email_verified_at')
                    ->label('E-mail vérifié')
                    ->boolean()
                    ->alignCenter(),

                IconColumn::make('two_factor_confirmed_at')
                    ->label('2FA')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('j M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rôle')
                    ->relationship('roles', 'name')
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make()->label('Modifier'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aucun compte');
    }
}
