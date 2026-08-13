<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
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
        // Dépôt d'un dossier : 5 tentatives par minute et par IP (§6.5).
        RateLimiter::for('depot', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Suivi d'un dossier : 5 tentatives par minute et par IP (§4).
        RateLimiter::for('suivi', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Téléversement par tranches : un dossier complet représente déjà
        // plusieurs dizaines de requêtes légitimes.
        RateLimiter::for('televersement', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
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
