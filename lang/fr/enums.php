<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Libellés des enums
    |--------------------------------------------------------------------------
    |
    | Les libellés affichés pour les enums PHP natifs du domaine. Traduire le
    | site dans une autre langue revient à copier ce fichier dans lang/<code>/.
    |
    */

    'application_status' => [
        'nouveau' => 'Nouveau',
        'en_cours' => 'En cours de traitement',
        'incomplet' => 'Dossier incomplet',
        'valide' => 'Validé',
        'rejete' => 'Rejeté',
    ],

    'section_type' => [
        'hero' => 'Bandeau principal',
        'texte_image' => 'Texte et image',
        'cartes' => 'Cartes',
        'etapes' => 'Étapes numérotées',
        'chiffres' => 'Chiffres clés',
        'galerie' => 'Galerie d\'images',
        'citation' => 'Citation',
        'cta' => 'Appel à l\'action',
        'logos' => 'Logos et labels',
    ],

    'section_type_description' => [
        'hero' => 'Grand bandeau en haut de page, avec titre et boutons.',
        'texte_image' => 'Un paragraphe et une image côte à côte.',
        'cartes' => 'Une grille de cartes, chacune avec un titre et un texte.',
        'etapes' => 'Un déroulé numéroté, pour expliquer un processus.',
        'chiffres' => 'Quelques chiffres mis en avant. N\'y mettez que des données réelles.',
        'galerie' => 'Plusieurs images avec légende.',
        'citation' => 'Une citation mise en valeur, avec son auteur.',
        'cta' => 'Un encadré invitant à passer à l\'action.',
        'logos' => 'Une bande de logos ou de labels.',
    ],

    'document_scan_status' => [
        'en_attente' => 'Analyse en attente',
        'sain' => 'Fichier sain',
        'infecte' => 'Fichier infecté',
        'indisponible' => 'Analyse indisponible',
    ],

    'document_type' => [
        'cv' => 'CV au format canadien',
        'tcf_tef' => 'Résultat TCF / TEF',
        'passeport' => 'Passeport',
        'diplome' => 'Diplôme',
        'autre' => 'Autre document',
    ],

];
