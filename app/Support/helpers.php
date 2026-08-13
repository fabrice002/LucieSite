<?php

use App\Support\SiteContentRepository;

if (! function_exists('content')) {
    /**
     * Récupère un texte éditable d'une page publique.
     *
     * Aucun texte visible ne doit être écrit en dur dans une vue publique :
     * il passe par ce helper et devient modifiable depuis le back-office.
     *
     * Exemple : content('accueil.hero_titre', 'Titre par défaut')
     */
    function content(string $path, ?string $default = null): string
    {
        return app(SiteContentRepository::class)->get($path, $default);
    }
}
