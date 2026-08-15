<?php

use App\Support\SiteContentRepository;
use App\Support\SiteSettingRepository;

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

if (! function_exists('content_filled')) {
    /**
     * Le texte s'il est rédigé, sinon null.
     *
     * À préférer à content() partout où un contenu manquant doit faire
     * disparaître l'élément plutôt qu'afficher « [À COMPLÉTER…] » en ligne.
     */
    function content_filled(string $path): ?string
    {
        return app(SiteContentRepository::class)->filled($path);
    }
}

if (! function_exists('setting')) {
    /**
     * Récupère un réglage d'apparence.
     *
     * Repli en cascade : valeur enregistrée, puis défaut passé ici, puis
     * config/brand.php. Sans rien en base, le site garde son apparence livrée.
     *
     * Exemple : setting('couleur_principale', '#1d4ed8')
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SiteSettingRepository::class)->get($key, $default);
    }
}
