<?php

namespace App\Filament\Resources\TeamMembers\Schemas;

use App\Filament\Forms\ContentImage;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TeamMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Membre de l\'équipe')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('role')
                        ->label('Fonction')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('bio')
                        ->label('Présentation')
                        ->rows(4)
                        ->maxLength(1000)
                        ->columnSpanFull(),

                    ContentImage::upload('photo_path', 'images/equipe')->label('Photo'),
                    ContentImage::alt('photo_alt', 'photo_path'),

                    Toggle::make('is_published')
                        ->label('Publié')
                        ->helperText('La section « équipe » n\'apparaît sur la page À propos que si au moins un membre est publié.'),

                    TextInput::make('sort_order')
                        ->label('Ordre d\'affichage')
                        ->numeric()
                        ->default(0),
                ]),
        ]);
    }
}
