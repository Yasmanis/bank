<?php

namespace App\Policies;

use App\Models\BankList;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BankListPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BankList $bankList): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BankList $bankList): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BankList $bankList): bool
    {
        // El Admin con permiso puede borrar cualquiera.
        if ($user->can('list.view_all')) {
            return true;
        }
        // El usuario normal solo puede borrar la suya Y si tiene el permiso de borrar.
        return $user->id === $bankList->user_id && $user->can('list.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BankList $bankList): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BankList $bankList): bool
    {
        return false;
    }
}
