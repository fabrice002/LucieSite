<?php

namespace App\Policies;

use App\Models\SiteContent;
use App\Models\User;

/**
 * Les textes du site public ne sont modifiables que par `admin`.
 *
 * Créer ou supprimer un bloc est interdit à tous : les clés sont référencées
 * dans les vues, en supprimer une casserait une page.
 */
class SiteContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, SiteContent $siteContent): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SiteContent $siteContent): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, SiteContent $siteContent): bool
    {
        return false;
    }
}
