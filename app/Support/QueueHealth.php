<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Surveille la file d'attente des e-mails.
 *
 * Un e-mail qui ne part pas sans que personne ne s'en aperçoive est le risque
 * principal des notifications : le candidat n'est pas prévenu, et le cabinet
 * croit l'avoir informé. Un job qui stagne signale presque toujours un worker
 * arrêté.
 */
class QueueHealth
{
    /** Au-delà de ce délai, un job en attente n'est plus normal. */
    public const SEUIL_MINUTES = 10;

    /**
     * Détermine si la file semble à l'arrêt.
     */
    public function estBloquee(): bool
    {
        return $this->attenteDepuis() !== null;
    }

    /**
     * Depuis quand le plus ancien job attend, s'il dépasse le seuil.
     */
    public function attenteDepuis(): ?Carbon
    {
        // Le pilote « database » est le seul à exposer une table lisible ici.
        if (config('queue.default') !== 'database') {
            return null;
        }

        try {
            $plusAncien = DB::table('jobs')->min('available_at');
        } catch (Throwable) {
            // Table absente, base injoignable : ce n'est pas au tableau de bord
            // de tomber pour autant.
            return null;
        }

        if (! is_numeric($plusAncien)) {
            return null;
        }

        $depuis = Carbon::createFromTimestamp((int) $plusAncien);

        return $depuis->lessThanOrEqualTo(now()->subMinutes(self::SEUIL_MINUTES))
            ? $depuis
            : null;
    }

    /**
     * Nombre de jobs actuellement en attente.
     */
    public function enAttente(): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        try {
            return DB::table('jobs')->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
