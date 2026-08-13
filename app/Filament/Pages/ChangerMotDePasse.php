<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Choix d'un nouveau mot de passe.
 *
 * Un compte créé par un administrateur porte un mot de passe provisoire connu
 * de deux personnes. Tant qu'il n'a pas été remplacé, EnsurePasswordHasBeenChanged
 * ramène systématiquement ici.
 */
class ChangerMotDePasse extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $title = 'Choisir un mot de passe';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.changer-mot-de-passe';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'changer-mot-de-passe';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getSubheading(): string
    {
        return $this->utilisateur()->must_change_password
            ? 'Votre mot de passe est provisoire. Choisissez-en un nouveau pour accéder au back-office.'
            : 'Utilisez un mot de passe long, propre à ce site.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('password')
                    ->label('Nouveau mot de passe')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->different('current_password')
                    ->confirmed(),

                TextInput::make('password_confirmation')
                    ->label('Confirmer le mot de passe')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function enregistrer(): void
    {
        $donnees = $this->form->getState();

        $utilisateur = $this->utilisateur();
        $utilisateur->forceFill([
            'password' => $donnees['password'],
            'must_change_password' => false,
        ])->save();

        $this->form->fill();

        Notification::make()
            ->success()
            ->title('Mot de passe enregistré.')
            ->send();

        $this->redirect(Dashboard::getUrl());
    }

    private function utilisateur(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
