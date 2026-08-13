<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    /** Ce que chaque rôle autorise, affiché en clair sous les cases à cocher. */
    private const DROITS = [
        'admin' => 'Tous les droits : dossiers, témoignages, textes du site, comptes et suppressions.',
        'agent' => 'Consultation des dossiers, changement de statut et notes internes. Ni suppression, ni textes du site.',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Adresse e-mail')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('password')
                        ->label('Mot de passe')
                        ->password()
                        ->revealable()
                        ->rule(Password::default())
                        // Obligatoire à la création, laissé vide en modification
                        // pour conserver le mot de passe existant.
                        ->required(fn (?User $record): bool => $record === null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                        ->helperText(fn (?User $record): string => $record === null
                            ? 'Communiquez-le à la personne concernée par un canal sûr.'
                            : 'Laissez vide pour conserver le mot de passe actuel.')
                        ->columnSpanFull(),
                ]),

            Section::make('Rôle')
                ->description('Un compte sans rôle ne peut pas entrer dans le back-office.')
                ->schema([
                    CheckboxList::make('roles')
                        ->hiddenLabel()
                        ->relationship('roles', 'name')
                        ->options(fn (): array => Role::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->descriptions(fn (): array => Role::query()
                            ->pluck('name', 'id')
                            ->map(fn (string $name): string => self::DROITS[$name] ?? '')
                            ->all())
                        // Un administrateur ne peut pas se retirer ses propres droits.
                        ->disableOptionWhen(fn (string $value, ?User $record): bool => $record !== null
                            && $record->is(Auth::user())
                            && Role::find($value)?->name === 'admin'),
                ]),
        ]);
    }
}
