<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureTrustedProxies();
    }

    /**
     * Déclare les proxies de confiance.
     *
     * Configuré ici plutôt que dans bootstrap/app.php : à cet endroit, seul
     * env() est disponible, et il renvoie null dès que la configuration est
     * mise en cache — c'est-à-dire en production. Le réglage serait alors
     * silencieusement ignoré, et les limiteurs bloqueraient tout le monde.
     */
    protected function configureTrustedProxies(): void
    {
        $proxies = config('proxies.trusted');

        if (blank($proxies)) {
            return;
        }

        TrustProxies::at(
            $proxies === '*' ? '*' : array_map(trim(...), explode(',', (string) $proxies)),
        );
    }

    /**
     * Configure the rate limiters of the public site.
     *
     * Chaque limiteur est nommé, et donc doté de son propre compteur. La forme
     * anonyme « throttle:5,1 » partage au contraire une seule clé domaine|IP
     * entre toutes les routes : les nombreuses requêtes d'un envoi par tranches
     * épuiseraient alors le quota de soumission du formulaire.
     */
    protected function configureRateLimiting(): void
    {
        /*
         | Dépôt d'un dossier : 5 tentatives par minute et par IP.
         |
         | Une soumission légitime est rare : un candidat dépose son dossier une
         | fois. Le plafond reste donc bas.
         |
         | Si des blocages sont constatés sur le terrain — un cybercafé d'où
         | plusieurs candidats déposent le même jour, une salle de formation —
         | remplacer la clé par une combinaison de l'IP et de l'adresse e-mail :
         |
         |     ->by(sha1($request->ip().'|'.$request->string('email')->lower()))
         |
         | Deux personnes distinctes cessent alors de se gêner, tout en freinant
         | toujours les envois répétés d'un même expéditeur.
         */
        RateLimiter::for('depot', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));

        /*
         | Suivi d'un dossier : deux limites simultanées.
         |
         | Au Cameroun, une grande part des abonnés mobiles partagent une même
         | adresse IP publique via le CGNAT, et les cybercafés davantage encore.
         | Un plafond par IP seule bloquerait des candidats parfaitement
         | légitimes qui n'ont rien fait d'autre que consulter leur dossier.
         |
         | La première limite porte donc sur le couple IP + référence : deux
         | candidats derrière la même IP ne se gênent plus. La seconde, purement
         | par IP, reste là pour freiner une énumération massive de références.
         */
        RateLimiter::for('suivi', fn (Request $request) => [
            Limit::perMinute(10)->by(sha1($request->ip().'|'.$request->string('reference')->trim()->upper())),
            Limit::perMinute(60)->by((string) $request->ip()),
        ]);

        // Téléversement par tranches : un dossier complet représente déjà
        // plusieurs dizaines de requêtes légitimes.
        RateLimiter::for('televersement', fn (Request $request) => Limit::perMinute(300)->by((string) $request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
