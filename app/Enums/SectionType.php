<?php

namespace App\Enums;

/**
 * Les types de blocs disponibles dans le constructeur de pages.
 *
 * Chaque type correspond à un partial sous resources/views/sections/ et à un
 * formulaire dans le Builder de Filament. Ajouter un type revient à ajouter une
 * valeur ici, un partial, et son formulaire — rien d'autre.
 */
enum SectionType: string
{
    case Hero = 'hero';
    case TexteImage = 'texte_image';
    case Cartes = 'cartes';
    case Etapes = 'etapes';
    case Chiffres = 'chiffres';
    case Galerie = 'galerie';
    case Citation = 'citation';
    case Cta = 'cta';
    case Logos = 'logos';

    /**
     * Get the human readable label, translated into the current locale.
     */
    public function label(): string
    {
        return __('enums.section_type.'.$this->value);
    }

    /**
     * Short description shown in the back office when picking a block.
     */
    public function description(): string
    {
        return __('enums.section_type_description.'.$this->value);
    }

    /**
     * Icon shown next to the block in the builder.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Hero => 'heroicon-o-photo',
            self::TexteImage => 'heroicon-o-view-columns',
            self::Cartes => 'heroicon-o-squares-2x2',
            self::Etapes => 'heroicon-o-list-bullet',
            self::Chiffres => 'heroicon-o-chart-bar',
            self::Galerie => 'heroicon-o-rectangle-stack',
            self::Citation => 'heroicon-o-chat-bubble-bottom-center-text',
            self::Cta => 'heroicon-o-megaphone',
            self::Logos => 'heroicon-o-building-office-2',
        };
    }

    /**
     * The Blade partial that renders this block.
     */
    public function view(): string
    {
        return 'sections.'.$this->value;
    }

    /**
     * Get every case keyed by value, for select inputs and filters.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
