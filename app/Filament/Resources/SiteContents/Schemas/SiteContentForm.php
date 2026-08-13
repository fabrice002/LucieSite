<?php

namespace App\Filament\Resources\SiteContents\Schemas;

use App\Models\SiteContent;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Formulaire généré dynamiquement à partir des clés du bloc.
 *
 * L'administratrice ne voit jamais de JSON : un champ par clé, avec un type
 * choisi selon le nom de la clé et la longueur du texte existant.
 */
class SiteContentForm
{
    /** Au-delà de cette longueur, un texte mérite une zone multiligne. */
    private const SEUIL_TEXTAREA = 90;

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema(fn (?SiteContent $record): array => self::champs($record)),
        ]);
    }

    /**
     * Build one field per key found in the block.
     *
     * @return list<TextInput|Textarea|RichEditor>
     */
    private static function champs(?SiteContent $record): array
    {
        if ($record === null) {
            return [];
        }

        $champs = [];

        // Le contenu d'un bloc est un JSON à plat de paires clé/valeur textuelles.
        foreach ($record->content as $cle => $valeur) {
            $champs[] = self::champ($cle, $valeur);
        }

        return $champs;
    }

    private static function champ(string $cle, string $valeur): TextInput|Textarea|RichEditor
    {
        $chemin = 'content.'.$cle;
        $libelle = self::libelle($cle);

        // Les clés en _html accueillent du texte mis en forme.
        if (str_ends_with($cle, '_html')) {
            return RichEditor::make($chemin)
                ->label($libelle)
                ->helperText($cle)
                ->columnSpanFull();
        }

        if (self::estLong($cle, $valeur)) {
            return Textarea::make($chemin)
                ->label($libelle)
                ->helperText($cle)
                ->rows(4)
                ->maxLength(5000)
                ->columnSpanFull();
        }

        return TextInput::make($chemin)
            ->label($libelle)
            ->helperText($cle)
            ->maxLength(255)
            ->columnSpanFull();
    }

    /**
     * Decide whether a value deserves a multi-line field.
     */
    private static function estLong(string $cle, string $valeur): bool
    {
        if (mb_strlen($valeur) > self::SEUIL_TEXTAREA) {
            return true;
        }

        // Un placeholder est court aujourd'hui mais accueillera un paragraphe.
        return (bool) preg_match(
            '/(texte|description|intro|paragraphe|message|adresse|mention|baseline|sous_titre|aide)/',
            $cle,
        );
    }

    /**
     * Turn "section_services_titre" into "Section services titre".
     */
    private static function libelle(string $cle): string
    {
        return Str::of($cle)
            ->replace('_', ' ')
            ->trim()
            ->ucfirst()
            ->value();
    }
}
