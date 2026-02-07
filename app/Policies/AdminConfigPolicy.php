<?php

namespace App\Policies;

use App\Models\AdminConfig;
use App\Models\User;

class AdminConfigPolicy
{
    /**
     * ¿Puede ver esta configuración específica?
     */
    public function view(User $user, AdminConfig $adminConfig): bool
    {
        return $user->hasRole('super-admin') || $user->id === $adminConfig->user_id;
    }

    /**
     * ¿Puede actualizar esta configuración?
     */
    public function update(User $user, AdminConfig $adminConfig): bool
    {
        return $user->hasRole('super-admin') || $user->id === $adminConfig->user_id;
    }

    /**
     * ¿Puede eliminar esta configuración?
     */
    public function delete(User $user, AdminConfig $adminConfig): bool
    {
        return $user->hasRole('super-admin');
    }
}
