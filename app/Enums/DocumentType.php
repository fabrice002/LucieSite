<?php

namespace App\Enums;

enum DocumentType: string
{
    case Cv = 'cv';
    case TcfTef = 'tcf_tef';
    case Passeport = 'passeport';
    case Diplome = 'diplome';
    case Autre = 'autre';

    /**
     * Get the human readable label, translated into the current locale.
     */
    public function label(): string
    {
        return __('enums.document_type.'.$this->value);
    }

    /**
     * Determine whether an application cannot be submitted without this type.
     */
    public function isRequired(): bool
    {
        return match ($this) {
            self::Cv, self::TcfTef => true,
            self::Passeport, self::Diplome, self::Autre => false,
        };
    }

    /**
     * Determine whether an application may hold several files of this type.
     */
    public function allowsMultiple(): bool
    {
        return match ($this) {
            self::Diplome, self::Autre => true,
            self::Cv, self::TcfTef, self::Passeport => false,
        };
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
