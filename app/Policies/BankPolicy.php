<?php

namespace App\Policies;

use App\Models\Bank;
use App\Models\User;

class BankPolicy
{
    /**
     * El Super Admin puede todo (vía Gate::before).
     * Los Admins solo gestionan los bancos donde admin_id es su ID.
     */
    public function view(User $user, Bank $bank) {
        return $user->can('banks.index') || $user->can('banks.show');
    }

    public function update(User $user, Bank $bank) {
        return $user->can('banks.update');
    }

    public function delete(User $user, Bank $bank) {
        return $user->can('banks.delete');
    }
}
