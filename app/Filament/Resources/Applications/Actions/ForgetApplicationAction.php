<?php

namespace App\Filament\Resources\Applications\Actions;

use App\Actions\SubmitApplication;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * « Effacer définitivement » : droit à l'effacement, exercé à la demande.
 *
 * Le dossier et tous ses fichiers disparaissent du disque privé. Seule la
 * ligne du journal d'activité subsiste, pour prouver que l'effacement a bien
 * eu lieu et par qui — sans conserver la moindre donnée du candidat.
 *
 * La confirmation impose de recopier la référence : sur une action
 * irréversible qui détruit des pièces d'identité, un simple « Oui » ne suffit
 * pas.
 */
class ForgetApplicationAction
{
    public static function make(): Action
    {
        return Action::make('effacer')
            ->label('Effacer définitivement')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->authorize(fn (Application $record): bool => Auth::user()?->can('forceDelete', $record) ?? false)
            ->modalHeading('Effacer définitivement ce dossier')
            ->modalDescription('Le dossier et toutes ses pièces seront supprimés du serveur. Cette action est irréversible : aucune restauration n\'est possible.')
            ->modalSubmitActionLabel('Effacer définitivement')
            ->schema(fn (Application $record): array => [
                TextInput::make('confirmation')
                    ->label('Recopiez la référence pour confirmer')
                    ->placeholder($record->reference)
                    ->required()
                    ->autocomplete(false)
                    ->rule(Rule::in([$record->reference]))
                    ->validationMessages([
                        'in' => 'La référence saisie ne correspond pas à celle du dossier.',
                    ]),
            ])
            ->action(function (Application $record): void {
                $reference = $record->reference;
                $disque = Storage::disk(SubmitApplication::DISK);
                $fichiers = 0;

                foreach ($record->documents as $document) {
                    if ($disque->exists($document->path)) {
                        $disque->delete($document->path);
                        $fichiers++;
                    }
                }

                $disque->deleteDirectory('documents/'.$reference);

                // Journalisé avant la suppression : après, le sujet n'existe
                // plus. On ne conserve que la référence et le nombre de pièces,
                // aucune donnée personnelle.
                activity('dossier')
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'reference' => $reference,
                        'fichiers' => $fichiers,
                        'motif' => 'Effacement à la demande',
                    ])
                    ->log('Dossier effacé définitivement');

                $record->forceDelete();

                Notification::make()
                    ->success()
                    ->title('Dossier effacé')
                    ->body("Le dossier {$reference} et ses {$fichiers} pièce(s) ont été supprimés du serveur.")
                    ->send();
            })
            ->successRedirectUrl(fn (): string => ApplicationResource::getUrl('index'));
    }
}
