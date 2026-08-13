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

];
