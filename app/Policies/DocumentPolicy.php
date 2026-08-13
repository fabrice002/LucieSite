<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Les documents contiennent des scans de passeports et de diplômes.
 * Leur accès est réservé aux membres authentifiés du cabinet.
 */
class DocumentPolicy
{
    /**
     * Determine whether the user can list documents.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'agent']);
    }

    /**
     * Determine whether the user can view and download the document.
     */
    public function view(User $user, Document $document): bool
    {
        return $user->hasAnyRole(['admin', 'agent']);
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, Document $document): bool
    {
        return $user->hasRole('admin');
    }
}
