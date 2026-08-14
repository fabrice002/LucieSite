<?php

namespace App\Policies;

use App\Models\ApplicationUpdate;
use App\Models\User;

/**
 * `admin` et `agent` informent le candidat ; seul `admin` peut effacer une
 * mise à jour déjà communiquée.
 */
class ApplicationUpdatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'agent']);
    }

    public function view(User $user, ApplicationUpdate $update): bool
    {
        return $user->hasAnyRole(['admin', 'agent']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'agent']);
    }

    public function update(User $user, ApplicationUpdate $update): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, ApplicationUpdate $update): bool
    {
        return $user->hasRole('admin');
    }
}
