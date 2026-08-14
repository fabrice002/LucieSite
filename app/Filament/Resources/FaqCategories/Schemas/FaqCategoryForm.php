<?php

namespace App\Filament\Resources\FaqCategories\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class FaqCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Thème')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom du thème')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, ?string $operation): void {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),

                    TextInput::make('slug')
                        ->label('Identifiant')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Toggle::make('is_published')
                        ->label('Publié')
                        ->default(true)
                        ->helperText('Un thème dépublié masque toutes ses questions.'),

                    TextInput::make('sort_order')
                        ->label('Ordre d\'affichage')
                        ->numeric()
                        ->default(0),
                ]),

            Section::make('Questions')
                ->description('Ajoutez-en autant que nécessaire. Aucune limite.')
                ->schema([
                    Repeater::make('faqs')
                        ->hiddenLabel()
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->reorderableWithDragAndDrop()
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                        ->addActionLabel('Ajouter une question')
                        ->defaultItems(1)
                        ->schema([
                            TextInput::make('question')
                                ->label('Question')
                                ->required()
                                ->maxLength(255),

                            RichEditor::make('answer')
                                ->label('Réponse')
                                ->required(),

                            Toggle::make('is_published')
                                ->label('Publiée')
                                ->default(true),
                        ]),
                ]),
        ]);
    }
}
