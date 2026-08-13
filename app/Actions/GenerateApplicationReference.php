<?php

namespace App\Actions;

use App\Models\Application;
use Illuminate\Support\Facades\DB;

/**
 * Génère la référence publique d'un dossier, au format LN-2026-00147.
 *
 * L'identifiant numérique n'est jamais exposé. La numérotation repart de 1
 * chaque année et tient compte des dossiers supprimés en douceur, afin qu'une
 * référence déjà communiquée à un candidat ne soit jamais réattribuée.
 */
class GenerateApplicationReference
{
    private const PREFIX = 'LN';

    public function __invoke(?int $year = null): string
    {
        $year ??= (int) now()->year;

        $prefix = self::PREFIX.'-'.$year.'-';

        // withTrashed() : une référence déjà utilisée ne doit jamais resservir.
        $last = Application::withTrashed()
            ->where('reference', 'like', $prefix.'%')
            ->when(
                DB::connection()->getDriverName() !== 'sqlite',
                fn ($query) => $query->lockForUpdate(),
            )
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = is_string($last)
            ? ((int) substr($last, strlen($prefix))) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
