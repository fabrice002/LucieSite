<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;

/**
 * Authentification à deux facteurs, dans le back-office.
 *
 * Toute la mécanique reste celle de Fortify, déjà installée et testée : cette
 * page ne fait qu'appeler ses actions. Aucune logique d'authentification n'est
 * réécrite ici.
 */
class Securite extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $title = 'Sécurité';

    protected static ?string $navigationLabel = 'Sécurité';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.securite';

    /** Les codes de récupération ne s'affichent qu'à la demande. */
    public bool $codesVisibles = false;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'securite';
    }

    public function getSubheading(): string
    {
        return 'Renforcez l\'accès à votre compte avec un code temporaire généré par votre téléphone.';
    }

    public function utilisateur(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function deuxFacteursActive(): bool
    {
        return $this->utilisateur()->hasEnabledTwoFactorAuthentication();
    }

    public function enAttenteDeConfirmation(): bool
    {
        $user = $this->utilisateur();

        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;
    }

    /**
     * @return list<string>
     */
    public function codesDeRecuperation(): array
    {
        if (! $this->deuxFacteursActive()) {
            return [];
        }

        /** @var list<string> */
        return $this->utilisateur()->recoveryCodes();
    }

    public function qrCode(): ?string
    {
        $user = $this->utilisateur();

        return $user->two_factor_secret !== null ? $user->twoFactorQrCodeSvg() : null;
    }

    public function cleManuelle(): ?string
    {
        $user = $this->utilisateur();

        return $user->two_factor_secret !== null ? decrypt($user->two_factor_secret) : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activer')
                ->label('Activer la double authentification')
                ->icon('heroicon-o-shield-check')
                ->visible(fn (): bool => $this->deuxFacteursDisponible()
                    && ! $this->deuxFacteursActive()
                    && ! $this->enAttenteDeConfirmation())
                ->action(function (EnableTwoFactorAuthentication $enable): void {
                    $enable($this->utilisateur());

                    Notification::make()
                        ->success()
                        ->title('Scannez le QR code, puis confirmez avec un code à 6 chiffres.')
                        ->send();
                }),

            Action::make('confirmer')
                ->label('Confirmer le code')
                ->icon('heroicon-o-check-badge')
                ->visible(fn (): bool => $this->enAttenteDeConfirmation())
                ->schema([
                    TextInput::make('code')
                        ->label('Code à 6 chiffres')
                        ->required()
                        ->numeric()
                        ->minLength(6)
                        ->maxLength(6),
                ])
                ->action(function (array $data, ConfirmTwoFactorAuthentication $confirm): void {
                    $confirm($this->utilisateur(), $data['code']);

                    Notification::make()
                        ->success()
                        ->title('Double authentification activée.')
                        ->body('Conservez vos codes de récupération en lieu sûr.')
                        ->send();
                }),

            Action::make('regenerer')
                ->label('Régénérer les codes de récupération')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->deuxFacteursActive())
                ->requiresConfirmation()
                ->modalHeading('Régénérer les codes de récupération')
                ->modalDescription('Les anciens codes cesseront immédiatement de fonctionner.')
                ->action(function (GenerateNewRecoveryCodes $generate): void {
                    $generate($this->utilisateur());
                    $this->codesVisibles = true;

                    Notification::make()->success()->title('Nouveaux codes générés.')->send();
                }),

            Action::make('desactiver')
                ->label('Désactiver')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->visible(fn (): bool => $this->deuxFacteursActive() || $this->enAttenteDeConfirmation())
                ->requiresConfirmation()
                ->modalHeading('Désactiver la double authentification')
                ->modalDescription('Votre compte ne sera plus protégé que par son mot de passe.')
                ->action(function (DisableTwoFactorAuthentication $disable): void {
                    $disable($this->utilisateur());
                    $this->codesVisibles = false;

                    Notification::make()->warning()->title('Double authentification désactivée.')->send();
                }),
        ];
    }

    public function basculerCodes(): void
    {
        $this->codesVisibles = ! $this->codesVisibles;
    }

    private function deuxFacteursDisponible(): bool
    {
        return Features::enabled(Features::twoFactorAuthentication());
    }
}
