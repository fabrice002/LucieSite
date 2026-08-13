<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Support\ApplicationHistory;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Candidat')
                ->columns(2)
                ->schema([
                    TextEntry::make('reference')
                        ->label('Référence')
                        ->copyable()
                        ->weight('bold'),

                    TextEntry::make('status')
                        ->label('Statut')
                        ->badge()
                        ->formatStateUsing(fn (ApplicationStatus $state): string => $state->label())
                        ->color(fn (ApplicationStatus $state): string => $state->color()),

                    TextEntry::make('full_name')->label('Nom complet'),
                    TextEntry::make('email')->label('Adresse e-mail')->copyable(),
                    TextEntry::make('phone')->label('Téléphone')->copyable(),
                    TextEntry::make('country_of_residence')->label('Pays de résidence'),
                    TextEntry::make('target_program')
                        ->label('Programme visé')
                        ->placeholder('Non précisé'),
                    TextEntry::make('created_at')
                        ->label('Déposé le')
                        ->dateTime('j F Y à H:i'),
                ]),

            Section::make('Message du candidat')
                ->collapsible()
                ->visible(fn (Application $record): bool => filled($record->message))
                ->schema([
                    TextEntry::make('message')
                        ->hiddenLabel()
                        ->prose(),
                ]),

            Section::make('Documents')
                ->description('Les fichiers sont stockés sur le disque privé et ne sont accessibles que depuis ici.')
                ->schema([
                    RepeatableEntry::make('documents')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('type')
                                ->label('Type')
                                ->badge()
                                ->formatStateUsing(fn ($state): string => $state->label()),

                            TextEntry::make('original_name')->label('Nom du fichier'),

                            TextEntry::make('size')
                                ->label('Taille')
                                ->formatStateUsing(fn (int $state): string => self::poids($state)),

                            TextEntry::make('id')
                                ->label('')
                                ->formatStateUsing(fn (): string => 'Télécharger')
                                ->badge()
                                ->color('primary')
                                ->url(fn ($record): string => route('documents.download', $record))
                                ->openUrlInNewTab(),
                        ]),
                ]),

            Section::make('Historique du dossier')
                ->description('Qui a fait quoi, et quand.')
                ->collapsible()
                ->schema([
                    TextEntry::make('historique')
                        ->hiddenLabel()
                        ->state(fn (Application $record): string => view(
                            'filament.infolists.historique-dossier',
                            ['entrees' => app(ApplicationHistory::class)($record)],
                        )->render())
                        ->html(),
                ]),

            Section::make('Traçabilité')
                ->collapsible()
                ->collapsed()
                ->columns(2)
                ->schema([
                    TextEntry::make('ip_address')
                        ->label('Adresse IP du dépôt')
                        ->placeholder('Inconnue'),
                    TextEntry::make('updated_at')
                        ->label('Dernière mise à jour')
                        ->dateTime('j F Y à H:i'),
                ]),
        ]);
    }

    /**
     * Render a byte count in a readable form.
     */
    private static function poids(int $octets): string
    {
        if ($octets >= 1_048_576) {
            return round($octets / 1_048_576, 1).' Mo';
        }

        return max(1, (int) round($octets / 1024)).' Ko';
    }
}
