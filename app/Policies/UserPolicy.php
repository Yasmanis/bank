<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $user, User $usuario)
    {
        return $user->hasRole(['admin', 'super-admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }
}
