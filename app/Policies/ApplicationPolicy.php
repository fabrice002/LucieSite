<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

/**
 * Les dossiers contiennent des données personnelles et des pièces d'identité.
 * `agent` consulte et fait avancer les dossiers ; seul `admin` supprime.
 */
class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'agent']);
    }

    public function view(User $user, Application $application): bool
    {
        return $user->hasAnyRole(['admin', 'agent']);
    }

    /**
     * Les dossiers proviennent exclusivement du formulaire public.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Application $application): bool
    {
        return $user->hasAnyRole(['admin', 'agent']);
    }

    public function delete(User $user, Application $application): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Application $application): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Application $application): bool
    {
        return $user->hasRole('admin');
    }
}
