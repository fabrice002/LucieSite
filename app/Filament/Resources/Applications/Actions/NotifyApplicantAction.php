<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\NotifyApplicant;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * « Informer le candidat » : change le statut et lui adresse un message.
 *
 * Le message se lira sur la page de suivi ; l'e-mail n'est qu'une alerte.
 */
class NotifyApplicantAction
{
    public static function make(): Action
    {
        return Action::make('informer')
            ->label('Informer le candidat')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->modalHeading('Informer le candidat')
            ->modalDescription('Le message sera lisible sur la page de suivi, après saisie de la référence et de l\'adresse e-mail.')
            ->modalSubmitActionLabel(fn (array $data): string => ($data['send_email'] ?? true)
                ? 'Enregistrer et envoyer'
                : 'Enregistrer sans envoyer')
            ->authorize(fn (Application $record): bool => Auth::user()?->can('update', $record) ?? false)
            ->fillForm(fn (Application $record): array => [
                'status' => $record->status->value,
                'public_message' => self::modele($record->status),
                'send_email' => true,
            ])
            ->schema([
                Select::make('status')
                    ->label('Nouveau statut')
                    ->options(ApplicationStatus::options())
                    ->required()
                    ->live()
                    // Le modèle suit le statut choisi, sauf si l'administratrice
                    // a déjà écrit son propre texte : on ne l'écrase jamais.
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        $actuel = trim((string) $get('public_message'));

                        if ($actuel !== '' && ! self::estUnModele($actuel)) {
                            return;
                        }

                        $set('public_message', self::modele(ApplicationStatus::tryFrom((string) $state)));
                    }),

                Textarea::make('public_message')
                    ->label('Message visible par le candidat')
                    ->rows(6)
                    ->maxLength(2000)
                    ->helperText('L\'e-mail d\'alerte ne contiendra pas ce message : le candidat devra le lire sur la page de suivi.'),

                Toggle::make('send_email')
                    ->label('Envoyer un e-mail d\'alerte')
                    ->helperText('Le candidat est prévenu qu\'une mise à jour l\'attend, sans en connaître le contenu.')
                    ->live()
                    ->default(true),
            ])
            ->action(function (Application $record, array $data, NotifyApplicant $notifier): void {
                /** @var User $auteur */
                $auteur = Auth::user();

                try {
                    $notifier->handle(
                        $record,
                        ApplicationStatus::tryFrom((string) ($data['status'] ?? '')),
                        $data['public_message'] ?? null,
                        (bool) ($data['send_email'] ?? false),
                        $auteur,
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Mise à jour impossible')
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Candidat informé')
                    ->body(($data['send_email'] ?? false)
                        ? 'L\'e-mail d\'alerte part en file d\'attente.'
                        : 'La mise à jour est enregistrée, sans envoi d\'e-mail.')
                    ->send();
            });
    }

    /**
     * Le modèle de message associé à un statut, éditable dans Textes du site.
     */
    private static function modele(?ApplicationStatus $status): string
    {
        return $status === null
            ? ''
            : content('suivi.modele_'.$status->value, '');
    }

    /**
     * Détermine si le texte saisi est encore l'un des modèles proposés.
     */
    private static function estUnModele(string $texte): bool
    {
        foreach (ApplicationStatus::cases() as $status) {
            if ($texte === trim(self::modele($status))) {
                return true;
            }
        }

        return false;
    }
}
