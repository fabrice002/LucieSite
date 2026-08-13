<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

/**
 * Les témoignages sont rédigés par l'administratrice. Un agent peut les
 * consulter mais ne publie rien au nom du cabinet.
 */
class TestimonialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'agent']);
    }

    public function view(User $user, Testimonial $testimonial): bool
    {
        return $user->hasAnyRole(['admin', 'agent']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Testimonial $testimonial): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $user->hasRole('admin');
    }

    public function reorder(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
