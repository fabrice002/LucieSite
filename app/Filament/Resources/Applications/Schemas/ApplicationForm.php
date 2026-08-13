<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\ApplicationStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Seuls le statut et les notes internes sont modifiables.
 *
 * Les informations du candidat sont celles qu'il a déclarées : les corriger
 * depuis le back-office fausserait la trace de ce qui a réellement été déposé.
 */
class ApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Traitement du dossier')
                ->schema([
                    Select::make('status')
                        ->label('Statut')
                        ->options(ApplicationStatus::options())
                        ->required()
                        ->native(false),

                    Textarea::make('internal_notes')
                        ->label('Notes internes')
                        ->helperText('Visibles uniquement dans le back-office. Le candidat n\'y a jamais accès.')
                        ->rows(6)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
