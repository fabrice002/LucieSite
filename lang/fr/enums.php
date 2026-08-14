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
