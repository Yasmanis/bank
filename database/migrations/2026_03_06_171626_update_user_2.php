<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $userRepository = new \App\Repositories\User\UserRepository();
        $mainUser = $userRepository->getUserByEmail('user@test.com');

        $user2 = $userRepository->getUserByEmail('user2@test.com');

        $user2->update([
            'main_user_id' => $mainUser->id
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $userRepository = new \App\Repositories\User\UserRepository();
        $user2 = $userRepository->getUserByEmail('user2@test.com');
        $user2->update([
            'main_user_id' => null
        ]);
    }
};
