<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $userRepository = new \App\Repositories\User\UserRepository();
        $mainUser = $userRepository->getUserByEmail('jose@test.com');
        $user = \App\Models\User::create([
            'name' => 'Lanyer',
            'email' => 'lanye@test.com',
            'password' => Hash::make('password123Segurojaja'),
            'main_user_id' => $mainUser->id
        ]);

        $user->syncRoles('user');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Models\User::where('email', 'lanye@test.com')->delete();
    }
};
