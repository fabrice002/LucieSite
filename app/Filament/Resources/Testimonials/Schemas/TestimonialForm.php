<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Témoignage')
                ->columns(2)
                ->schema([
                    TextInput::make('author_name')
                        ->label("Nom de l'auteur")
                        ->required()
                        ->maxLength(255),

                    TextInput::make('author_country')
                        ->label('Pays')
                        ->maxLength(255),

                    // Le programme obtenu vaut plus qu'un paragraphe élogieux :
                    // un témoignage sans contexte ne rassure personne, et c'est
                    // la forme que prennent les faux avis.
                    TextInput::make('author_programme')
                        ->label('Programme obtenu')
                        ->maxLength(255)
                        ->helperText('Par exemple « Entrée Express, 2025 ». Affiché sous le nom.')
                        ->columnSpanFull(),

                    Textarea::make('content')
                        ->label('Contenu')
                        ->required()
                        ->rows(6)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ]),

            Section::make('Média')
                ->columns(2)
                ->schema([
                    // Seule la photo des témoignages va sur le disque public :
                    // aucun document de candidat n'y est jamais stocké.
                    FileUpload::make('photo_path')
                        ->label('Photo')
                        ->disk('public')
                        ->directory('temoignages')
                        ->image()
                        ->imageEditor()
                        ->maxSize(2048)
                        ->helperText('Format carré recommandé. 2 Mo maximum.'),

                    TextInput::make('video_url')
                        ->label('Lien vidéo')
                        ->url()
                        ->maxLength(255)
                        ->helperText('Lien YouTube ou Vimeo.'),
                ]),

            Section::make('Publication')
                ->columns(2)
                ->schema([
                    Toggle::make('is_published')
                        ->label('Publié sur le site')
                        ->helperText('Un témoignage non publié reste invisible du public.'),

                    TextInput::make('sort_order')
                        ->label("Ordre d'affichage")
                        ->numeric()
                        ->default(0)
                        ->helperText('Les plus petits nombres apparaissent en premier.'),
                ]),
        ]);
    }
}
