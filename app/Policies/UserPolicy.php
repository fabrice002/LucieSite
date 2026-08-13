<?php

namespace App\Policies;

use App\Models\User;

/**
 * La gestion des comptes et des rôles est réservée à `admin`.
 *
 * Un administrateur ne peut ni se retirer son propre rôle, ni supprimer son
 * propre compte : c'est le garde-fou qui évite de se verrouiller dehors.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('admin') && ! $user->is($model);
    }
}
