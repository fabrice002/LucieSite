<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identité visuelle
    |--------------------------------------------------------------------------
    |
    | C'est LE seul endroit à modifier pour changer le logo du site. Il est
    | utilisé partout : en-tête et pied du site public, page de connexion,
    | back-office, et icône de l'onglet du navigateur.
    |
    */

    /*
     | Chemin du logo définitif, relatif au dossier public/.
     |
     | Exemple : 'images/logo.svg' pour public/images/logo.svg
     |
     | Laissé vide, le monogramme « LN » fourni est utilisé. Il s'adapte
     | automatiquement au thème clair comme sombre, ce qu'un fichier image
     | ne sait pas faire.
     */
    'logo' => env('BRAND_LOGO'),

    /*
     | Couleurs de l'icône d'onglet, régénérée par « php artisan ln:generate-icons ».
     */
    'icone_fond' => env('BRAND_ICON_BACKGROUND', '#1e40af'),
    'icone_trait' => env('BRAND_ICON_FOREGROUND', '#ffffff'),

    /*
     | Tracé du monogramme, sur une grille de 40 × 40.
     |
     | Les mêmes coordonnées servent au SVG affiché dans les pages et à la
     | génération des fichiers .ico et .png : le monogramme reste donc
     | identique partout, y compris dans l'onglet du navigateur.
     */
    'monogramme' => [
        'L' => [[6, 9], [10.6, 9], [10.6, 26.4], [18.8, 26.4], [18.8, 31], [6, 31]],
        'N' => [[21.4, 9], [25.8, 9], [33.4, 21.6], [33.4, 9], [38, 9], [38, 31], [33.6, 31], [26, 18.4], [26, 31], [21.4, 31]],
    ],

    /*
    |--------------------------------------------------------------------------
    | Apparence — valeurs de repli
    |--------------------------------------------------------------------------
    |
    | Ces valeurs servent tant que la table « site_settings » ne dit rien. Elles
    | reproduisent exactement l'apparence livrée : un site fraîchement installé,
    | ou dont on aurait vidé les réglages, reste cohérent.
    |
    | Elles ne se modifient pas ici au quotidien — la cliente règle tout depuis
    | le back-office, section « Apparence ».
    |
    */

    'apparence' => [
        'couleur_principale' => '#1d4ed8',
        'couleur_secondaire' => '#0f766e',
        'couleur_accent' => '#b45309',
        'couleur_texte_sur_principale' => '#ffffff',

        'police' => 'instrument-sans',
        'theme_sombre_actif' => true,

        'logo_clair' => null,
        'logo_sombre' => null,
        'favicon' => null,
    ],

    /*
     | Liste blanche des polices proposées.
     |
     | La clé est l'alias produit par Vite, soit le nom de famille en minuscules
     | et tirets. C'est lui que @fonts([...]) attend ; « famille » n'est que le
     | nom lisible, celui écrit dans la déclaration CSS.
     |
     | Chacune doit être déclarée dans « vite.config.js », qui les télécharge au
     | build et les sert depuis le domaine du site : aucune requête vers un
     | tiers, rien à aller chercher sur un serveur lointain en 3G.
     |
     | Une seule famille est envoyée par page, celle qui est choisie.
     */
    'polices' => [
        'instrument-sans' => [
            'famille' => 'Instrument Sans',
            'description' => 'Sobre et contemporaine. Celle du site livré.',
            'repli' => 'sans-serif',
        ],
        'inter' => [
            'famille' => 'Inter',
            'description' => 'Très lisible, y compris en petit corps sur téléphone.',
            'repli' => 'sans-serif',
        ],
        'public-sans' => [
            'famille' => 'Public Sans',
            'description' => 'Institutionnelle, proche des sites administratifs.',
            'repli' => 'sans-serif',
        ],
        'lora' => [
            'famille' => 'Lora',
            'description' => 'Sérif, d\'allure plus classique.',
            // Une sérif doit retomber sur une sérif : si le fichier n'arrive
            // pas — ce qui arrive en 3G — la page garde la même allure au lieu
            // de basculer en linéale.
            'repli' => 'serif',
        ],
    ],

];
