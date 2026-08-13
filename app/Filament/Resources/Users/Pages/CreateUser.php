<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\StaffAccountCreated;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->getRecord();

        // forceFill : ces deux colonnes ne sont volontairement pas
        // mass-assignables, un formulaire ne doit jamais pouvoir les décider.
        $user->forceFill([
            // Le mot de passe saisi par l'administrateur est provisoire.
            'must_change_password' => true,
            // Le compte est ouvert par une personne de confiance : inutile de
            // demander à son titulaire de vérifier son adresse.
            'email_verified_at' => now(),
        ])->save();

        $user->notify(new StaffAccountCreated(
            $user,
            $user->getRoleNames()->implode(', ') ?: 'aucun',
        ));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
