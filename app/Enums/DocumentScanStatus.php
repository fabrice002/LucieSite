<?php

namespace App\Enums;

enum DocumentScanStatus: string
{
    case EnAttente = 'en_attente';
    case Sain = 'sain';
    case Infecte = 'infecte';
    case Indisponible = 'indisponible';

    /**
     * Get the human readable label, translated into the current locale.
     */
    public function label(): string
    {
        return __('enums.document_scan_status.'.$this->value);
    }

    /**
     * Get the badge color used by the back office.
     */
    public function color(): string
    {
        return match ($this) {
            self::EnAttente => 'warning',
            self::Sain => 'success',
            self::Infecte => 'danger',
            self::Indisponible => 'gray',
        };
    }

    /**
     * Determine whether a document in this state may leave the server.
     *
     * Seul un fichier reconnu infecté est retenu. Une analyse en attente ou
     * indisponible ne bloque rien : le cabinet doit pouvoir travailler même
     * sans antivirus installé.
     */
    public function allowsDownload(): bool
    {
        return $this !== self::Infecte;
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
