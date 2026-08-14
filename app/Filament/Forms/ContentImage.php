<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;

/**
 * Champ image réutilisable pour tout le contenu éditorial.
 *
 * Les règles du cahier des charges sont posées une seule fois ici : disque
 * public, éditeur d'image, redimensionnement à 1600 px, 4 Mo maximum, et texte
 * alternatif obligatoire dès qu'une image est présente.
 *
 * Les visiteurs sont en 3G : une photo de 3 Mo non redimensionnée ruine la page.
 */
class ContentImage
{
    /** Largeur cible au téléversement. Au-delà, l'image est réduite. */
    public const LARGEUR_MAX = 1600;

    /**
     * Le champ de téléversement.
     */
    public static function upload(string $name = 'image_path', string $repertoire = 'images/contenu'): FileUpload
    {
        return FileUpload::make($name)
            ->label('Image')
            ->disk('public')
            ->directory($repertoire)
            ->visibility('public')
            ->image()
            // Éditeur natif de Filament : recadrage et rotation, sans
            // dépendance supplémentaire.
            ->imageEditor()
            ->imageEditorAspectRatios([null, '16:9', '4:3', '1:1'])
            ->imageResizeMode('contain')
            ->imageResizeTargetWidth((string) self::LARGEUR_MAX)
            ->maxSize(4096)
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->helperText('JPEG, PNG ou WebP, 4 Mo maximum. L\'image est réduite à '.self::LARGEUR_MAX.' px de large.')
            // Le fichier disparaît du disque quand on le retire du formulaire.
            ->deleteUploadedFileUsing(fn (string $file) => Storage::disk('public')->delete($file));
    }

    /**
     * Le texte alternatif, obligatoire dès qu'une image est déposée.
     */
    public static function alt(string $name = 'image_alt', string $champImage = 'image_path'): TextInput
    {
        return TextInput::make($name)
            ->label('Texte alternatif')
            ->maxLength(255)
            ->required(fn (Get $get): bool => filled($get($champImage)))
            ->helperText('Décrivez l\'image en une phrase. Lu par les lecteurs d\'écran et par les moteurs de recherche.');
    }
}
