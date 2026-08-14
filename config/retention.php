<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Conservation des dossiers
    |--------------------------------------------------------------------------
    |
    | Un dossier contient des scans de passeports et de diplômes. Le conserver
    | indéfiniment n'est ni utile au cabinet, ni acceptable pour le candidat.
    |
    */

    /*
     | Délai, en mois, au-delà duquel un dossier sans aucune activité est
     | définitivement supprimé — fichiers compris.
     |
     | L'activité couvre le dépôt, tout changement de statut et tout message
     | adressé au candidat : un dossier réellement suivi ne se purge jamais.
     */
    'months' => (int) env('RETENTION_MONTHS', 36),

    /*
     | Nombre de jours de préavis. Un rapport part vers les comptes « admin »
     | avec la liste des références concernées, afin de permettre une exception
     | avant l'effacement.
     */
    'notice_days' => (int) env('RETENTION_NOTICE_DAYS', 30),

    /*
     | Version courante de la politique de confidentialité.
     |
     | À incrémenter à chaque modification substantielle du texte : elle est
     | enregistrée avec le consentement de chaque candidat, faute de quoi on ne
     | saurait pas à quoi il a consenti.
     */
    'privacy_version' => env('PRIVACY_VERSION', '2026-01'),

];
