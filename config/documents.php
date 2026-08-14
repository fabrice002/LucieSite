<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Analyse antivirus des pièces déposées
    |--------------------------------------------------------------------------
    |
    | La validation « mimes » rejette un exécutable renommé, mais pas un PDF
    | légitimement formé porteur de JavaScript. Ces fichiers sont ouverts chaque
    | jour par le cabinet : une analyse ClamAV apporte une seconde barrière.
    |
    | L'analyse est volontairement DÉGRADABLE. Si ClamAV est absent ou
    | injoignable, le dépôt aboutit quand même et le document est marqué
    | « indisponible » : un antivirus en panne ne doit jamais empêcher un
    | candidat de déposer son dossier.
    |
    */

    'scan' => [

        // Désactivée par défaut : l'installation en développement ne réclame
        // pas ClamAV. À activer en production (voir DEPLOIEMENT.md).
        'enabled' => env('DOCUMENT_SCAN_ENABLED', false),

        // Binaire client de ClamAV. « clamdscan » interroge le démon déjà
        // chargé en mémoire, bien plus rapide que « clamscan ».
        'command' => env('DOCUMENT_SCAN_COMMAND', 'clamdscan'),

        // Secondes au-delà desquelles on renonce à analyser. Un scan qui traîne
        // ne doit pas immobiliser le worker.
        'timeout' => (int) env('DOCUMENT_SCAN_TIMEOUT', 60),

    ],

];
