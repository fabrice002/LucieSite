<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Securite;
use App\Http\Middleware\EnsurePasswordHasBeenChanged;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Volontairement pas de ->login() : l'authentification reste celle du
            // starter kit (Fortify), avec sa 2FA, sa vérification d'e-mail et sa
            // limitation de tentatives. Un second écran de connexion ferait
            // doublon et affaiblirait l'ensemble.
            ->brandName(config('app.name', 'LN Immigration'))
            // Page de profil native, rendue dans la mise en page du panel :
            // le nom, l'adresse e-mail et le mot de passe se modifient ici,
            // et la 2FA sur la page Sécurité juste à côté.
            ->profile(isSimple: false)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->userMenuItems([
                'profile' => fn (Action $action): Action => $action->label('Mon profil'),
                'securite' => Action::make('securite')
                    ->label('Sécurité')
                    ->icon('heroicon-o-shield-check')
                    ->url(fn (): string => Securite::getUrl()),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Un mot de passe provisoire doit être remplacé avant d'aller
                // plus loin : le panel donne accès à des pièces d'identité.
                EnsurePasswordHasBeenChanged::class,
            ]);
    }
}
