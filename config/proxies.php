<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confiance
    |--------------------------------------------------------------------------
    |
    | Derrière un reverse proxy non déclaré, Laravel voit l'adresse du proxy et
    | non celle du visiteur : TOUTES les requêtes partagent alors la même IP, et
    | les limiteurs de débit bloquent tout le monde après quelques
    | consultations. C'est la panne la plus déroutante à diagnostiquer, parce
    | que rien n'est en erreur.
    |
    | Valeurs acceptées pour TRUSTED_PROXIES :
    |
    |   (vide)            aucun proxy — développement, ou accès direct
    |   *                 tous les proxies. À ne poser QUE si le serveur n'est
    |                     joignable que par le proxy : sinon l'en-tête
    |                     X-Forwarded-For devient falsifiable et le limiteur se
    |                     contourne en changeant d'IP à volonté.
    |   10.0.0.1,10.0.0.2 liste explicite
    |   10.0.0.0/8        plage
    |
    */

    'trusted' => env('TRUSTED_PROXIES'),

];
