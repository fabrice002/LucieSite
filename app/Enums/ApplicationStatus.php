<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Nouveau = 'nouveau';
    case EnCours = 'en_cours';
    case Incomplet = 'incomplet';
    case Valide = 'valide';
    case Rejete = 'rejete';

    /**
     * Get the human readable label, translated into the current locale.
     */
    public function label(): string
    {
        return __('enums.application_status.'.$this->value);
    }

    /**
     * Get the badge color used by the back office.
     */
    public function color(): string
    {
        return match ($this) {
            self::Nouveau => 'info',
            self::EnCours => 'warning',
            self::Incomplet => 'danger',
            self::Valide => 'success',
            self::Rejete => 'gray',
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
