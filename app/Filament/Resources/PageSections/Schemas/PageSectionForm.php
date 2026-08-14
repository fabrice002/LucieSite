<?php

namespace App\Filament\Resources\PageSections\Schemas;

use App\Enums\SectionType;
use App\Filament\Forms\SectionBlocks;
use App\Models\PageSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PageSectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Le bloc')
                ->columns(2)
                ->schema([
                    Select::make('page')
                        ->label('Page')
                        ->options(PageSection::pages())
                        ->required()
                        ->default('accueil'),

                    Select::make('type')
                        ->label('Type de bloc')
                        ->options(SectionType::options())
                        ->required()
                        ->live()
                        // Changer de type change le formulaire : on repart d'un
                        // contenu vierge plutôt que de garder des champs qui
                        // n'ont plus de sens.
                        ->afterStateUpdated(fn (Set $set) => $set('data', []))
                        ->disabledOn('edit')
                        ->helperText(function (Get $get, ?string $operation): ?string {
                            if ($operation === 'edit') {
                                return 'Le type ne se change pas après création. Supprimez le bloc et recréez-le si besoin.';
                            }

                            return SectionType::tryFrom((string) $get('type'))?->description();
                        }),

                    Toggle::make('is_published')
                        ->label('Publié')
                        ->default(true)
                        ->helperText('Un bloc dépublié disparaît de la page, sans être supprimé.'),

                    TextInput::make('sort_order')
                        ->label('Ordre sur la page')
                        ->numeric()
                        ->default(0),
                ]),

            // Le contenu du bloc vit sous « data », et son formulaire dépend du
            // type choisi juste au-dessus.
            Section::make('Contenu')
                ->visible(fn (Get $get): bool => filled($get('type')))
                ->heading(fn (Get $get): string => SectionType::tryFrom((string) $get('type'))?->label() ?? 'Contenu')
                ->schema([
                    Group::make()
                        ->statePath('data')
                        ->schema(fn (Get $get): array => match ($type = SectionType::tryFrom((string) $get('type'))) {
                            null => [],
                            default => SectionBlocks::pour($type),
                        }),
                ]),
        ]);
    }
}
