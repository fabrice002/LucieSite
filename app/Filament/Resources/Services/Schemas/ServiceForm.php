<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Filament\Forms\ContentImage;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Le service')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Titre')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        // L'adresse se remplit toute seule à la création, et ne
                        // bouge plus ensuite : la changer casserait les liens
                        // déjà partagés et le référencement acquis.
                        ->afterStateUpdated(function (?string $state, Set $set, ?string $operation): void {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),

                    TextInput::make('slug')
                        ->label('Adresse de la page')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->prefix(url('/services').'/')
                        ->helperText('Évitez de la modifier une fois la page publiée.'),

                    Textarea::make('summary')
                        ->label('Résumé')
                        ->required()
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Deux à trois lignes, affichées sur la carte du service.')
                        ->columnSpanFull(),

                    RichEditor::make('body')
                        ->label('Contenu de la page')
                        ->columnSpanFull(),
                ]),

            // Périmètre de la prestation.
            //
            // Le flou sur ce qui est compris est le principal terrain des
            // litiges dans ce secteur. Énoncer ce qui n'est PAS compris protège
            // le candidat autant que le cabinet. Aucun plafond sur le nombre de
            // lignes.
            Section::make('Tarif et périmètre')
                ->description('Ce que la prestation comprend, et surtout ce qu\'elle ne comprend pas. Rien ne s\'affiche sur le site tant que ces listes sont vides.')
                ->columns(2)
                ->schema([
                    TextInput::make('price_note')
                        ->label('Tarif')
                        ->maxLength(255)
                        ->placeholder('Sur devis, après évaluation du profil')
                        ->helperText('Texte libre. N\'annoncez pas de montant que vous ne pourriez pas tenir.')
                        ->columnSpanFull(),

                    Repeater::make('included')
                        ->label('Compris dans la prestation')
                        ->simple(
                            TextInput::make('ligne')
                                ->hiddenLabel()
                                ->maxLength(255)
                                ->placeholder('Évaluation complète du profil'),
                        )
                        ->addActionLabel('Ajouter une ligne')
                        ->reorderable()
                        ->default([]),

                    Repeater::make('excluded')
                        ->label('Non compris')
                        ->simple(
                            TextInput::make('ligne')
                                ->hiddenLabel()
                                ->maxLength(255)
                                ->placeholder('Frais gouvernementaux et frais de visa'),
                        )
                        ->addActionLabel('Ajouter une ligne')
                        ->reorderable()
                        ->default([])
                        ->helperText('Les frais officiels, la traduction, les examens de langue… tout ce que le candidat paiera en plus.'),
                ]),

            Section::make('Présentation')
                ->columns(2)
                ->schema([
                    ContentImage::upload('image_path', 'images/services'),
                    ContentImage::alt('image_alt', 'image_path'),

                    TextInput::make('highlight')
                        ->label('Mention courte')
                        ->maxLength(60)
                        ->placeholder('Le plus demandé')
                        ->helperText('Facultatif. Petit badge affiché sur la carte.'),

                    TextInput::make('icon')
                        ->label('Icône')
                        ->maxLength(60)
                        ->placeholder('heroicon-o-globe-europe-africa')
                        ->helperText('Facultatif. Nom d\'une icône Heroicon, utilisée à défaut d\'image.'),
                ]),

            Section::make('Publication et référencement')
                ->columns(2)
                ->schema([
                    Toggle::make('is_published')
                        ->label('Publié')
                        ->helperText('Un service non publié n\'apparaît nulle part sur le site.'),

                    TextInput::make('sort_order')
                        ->label('Ordre d\'affichage')
                        ->numeric()
                        ->default(0),

                    TextInput::make('meta_title')
                        ->label('Titre pour les moteurs de recherche')
                        ->maxLength(255)
                        ->helperText('Laissez vide pour reprendre le titre du service.')
                        ->columnSpanFull(),

                    Textarea::make('meta_description')
                        ->label('Description pour les moteurs de recherche')
                        ->rows(2)
                        ->maxLength(255)
                        ->helperText('Environ 150 caractères. Laissez vide pour reprendre le résumé.')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
