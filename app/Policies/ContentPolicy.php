<?php

namespace App\Policies;

use App\Models\User;

/**
 * Le contenu éditorial du site — services, FAQ, sections, équipe.
 *
 * Il est géré par l'administratrice. Un `agent` traite des dossiers, pas la
 * vitrine : il n'a aucun accès à ces ressources.
 */
class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function reorder(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
