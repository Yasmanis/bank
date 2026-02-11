<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserConfig;

class UserConfigPolicy
{
    public function view(User $user, UserConfig $config) {
        return $user->hasRole(['admin', 'super-admin']);
    }

    public function update(User $user, UserConfig $config) {
        // Opción: El admin solo edita si él creó al usuario
        return $user->hasRole('super-admin') || $config->user->created_by === $user->id;
    }

    public function delete(User $user, UserConfig $config) {
        return $user->hasRole('super-admin');
    }

    public function create(User $user) {
        return $user->hasRole('super-admin');
    }
}
