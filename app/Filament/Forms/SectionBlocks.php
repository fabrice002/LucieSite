<?php

namespace App\Filament\Forms;

use App\Enums\SectionType;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * Les formulaires des neuf types de blocs du constructeur de pages.
 *
 * Ajouter un type revient à : une valeur dans SectionType, une entrée ici, et
 * un partial sous resources/views/sections/. Rien d'autre.
 *
 * Aucune liste n'a de plafond : la cliente ajoute autant de cartes, d'étapes ou
 * d'images qu'elle le souhaite.
 */
class SectionBlocks
{
    /**
     * Les champs du type demandé.
     *
     * @return list<Component>
     */
    public static function pour(SectionType $type): array
    {
        return match ($type) {
            SectionType::Hero => self::hero(),
            SectionType::TexteImage => self::texteImage(),
            SectionType::Cartes => self::cartes(),
            SectionType::Etapes => self::etapes(),
            SectionType::Chiffres => self::chiffres(),
            SectionType::Galerie => self::galerie(),
            SectionType::Citation => self::citation(),
            SectionType::Cta => self::cta(),
            SectionType::Logos => self::logos(),
        };
    }

    /**
     * @return list<Component>
     */
    private static function hero(): array
    {
        return [
            TextInput::make('sur_titre')->label('Sur-titre')->maxLength(120),
            TextInput::make('titre')->label('Titre')->required()->maxLength(255),
            Textarea::make('texte')->label('Texte')->rows(3)->maxLength(500),

            ContentImage::upload('image', 'images/sections')->label('Image de fond'),
            ContentImage::alt('image_alt', 'image'),

            TextInput::make('bouton_libelle')->label('Bouton principal')->maxLength(60),
            TextInput::make('bouton_url')->label('Lien du bouton principal')->maxLength(255),
            TextInput::make('bouton2_libelle')->label('Bouton secondaire')->maxLength(60),
            TextInput::make('bouton2_url')->label('Lien du bouton secondaire')->maxLength(255),
        ];
    }

    /**
     * @return list<Component>
     */
    private static function texteImage(): array
    {
        return [
            TextInput::make('titre')->label('Titre')->maxLength(255),
            RichEditor::make('texte')->label('Texte'),

            ContentImage::upload('image', 'images/sections'),
            ContentImage::alt('image_alt', 'image'),

            Select::make('position_image')
                ->label('Position de l\'image')
                ->options(['gauche' => 'À gauche', 'droite' => 'À droite'])
                ->default('droite')
                ->required(),
        ];
    }

    /**
     * @return list<Component>
     */
    private static function cartes(): array
    {
        return [
            TextInput::make('titre')->label('Titre de la section')->maxLength(255),
            Textarea::make('introduction')->label('Introduction')->rows(2)->maxLength(500),

            Repeater::make('cartes')
                ->label('Cartes')
                ->addActionLabel('Ajouter une carte')
                ->reorderableWithDragAndDrop()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['titre'] ?? null)
                ->defaultItems(3)
                ->schema([
                    TextInput::make('titre')->label('Titre')->required()->maxLength(255),
                    Textarea::make('texte')->label('Texte')->rows(3)->maxLength(500),
                    ContentImage::upload('image', 'images/sections'),
                    ContentImage::alt('image_alt', 'image'),
                    TextInput::make('icone')->label('Icône')->maxLength(60)
                        ->helperText('Facultatif, à défaut d\'image. Nom d\'une icône Heroicon.'),
                    TextInput::make('lien')->label('Lien')->maxLength(255),
                ]),
        ];
    }

    /**
     * @return list<Component>
     */
    private static function etapes(): array
    {
        return [
            TextInput::make('titre')->label('Titre de la section')->maxLength(255),
            Textarea::make('introduction')->label('Introduction')->rows(2)->maxLength(500),

            Repeater::make('etapes')
                ->label('Étapes')
                ->addActionLabel('Ajouter une étape')
                ->reorderableWithDragAndDrop()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['titre'] ?? null)
                ->defaultItems(3)
                ->schema([
                    TextInput::make('titre')->label('Titre')->required()->maxLength(255),
                    Textarea::make('description')->label('Description')->rows(3)->maxLength(500),
                    TextInput::make('icone')->label('Icône')->maxLength(60),
                ]),
        ];
    }

    /**
     * @return list<Component>
     */
    private static function chiffres(): array
    {
        return [
            TextInput::make('titre')->label('Titre de la section')->maxLength(255),

            Repeater::make('chiffres')
                ->label('Chiffres')
                ->addActionLabel('Ajouter un chiffre')
                ->reorderableWithDragAndDrop()
                // Livré vide, volontairement : ce secteur attire la fraude, et
                // une statistique inventée dessert la cliente autant que les
                // candidats.
                ->defaultItems(0)
                ->helperText('N\'indiquez que des données réelles et vérifiables. Aucune promesse de résultat, aucun taux de réussite.')
                ->itemLabel(fn (array $state): ?string => $state['valeur'] ?? null)
                ->schema([
                    TextInput::make('valeur')->label('Valeur')->required()->maxLength(30),
                    TextInput::make('libelle')->label('Libellé')->required()->maxLength(120),
                ]),
        ];
    }

    /**
     * @return list<Component>
     */
    private static function galerie(): array
    {
        return [
            TextInput::make('titre')->label('Titre de la section')->maxLength(255),

            Repeater::make('images')
                ->label('Images')
                ->addActionLabel('Ajouter une image')
                ->reorderableWithDragAndDrop()
                ->collapsible()
                ->defaultItems(1)
                ->schema([
                    ContentImage::upload('image', 'images/sections')->required(),
                    ContentImage::alt('image_alt', 'image'),
                    TextInput::make('legende')->label('Légende')->maxLength(255),
                ]),
        ];
    }

    /**
     * @return list<Component>
     */
    private static function citation(): array
    {
        return [
            Textarea::make('texte')->label('Citation')->required()->rows(4)->maxLength(1000),
            TextInput::make('auteur')->label('Auteur')->maxLength(120),
            TextInput::make('fonction')->label('Fonction')->maxLength(120),
        ];
    }

    /**
     * @return list<Component>
     */
    private static function cta(): array
    {
        return [
            TextInput::make('titre')->label('Titre')->required()->maxLength(255),
            Textarea::make('texte')->label('Texte')->rows(2)->maxLength(500),
            TextInput::make('bouton_libelle')->label('Libellé du bouton')->maxLength(60),
            TextInput::make('bouton_url')->label('Lien du bouton')->maxLength(255),
        ];
    }

    /**
     * @return list<Component>
     */
    private static function logos(): array
    {
        return [
            TextInput::make('titre')->label('Titre de la section')->maxLength(255),

            Repeater::make('logos')
                ->label('Logos et labels')
                ->addActionLabel('Ajouter un logo')
                ->reorderableWithDragAndDrop()
                ->defaultItems(1)
                ->helperText('N\'affichez que des appartenances réelles. Aucun agrément que le cabinet ne détient pas.')
                ->schema([
                    ContentImage::upload('image', 'images/sections')->required(),
                    ContentImage::alt('image_alt', 'image'),
                    TextInput::make('legende')->label('Légende')->maxLength(120),
                    TextInput::make('lien')->label('Lien')->maxLength(255),
                ]),
        ];
    }
}
