<?php

use App\Models\User;
use App\Repositories\User\UserRepository;
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

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('main_user_id')->nullable();
        });

        $userRepository = new UserRepository();
        $mainUser = $userRepository->getUserByEmail('yurislier@test.com');


        $defaultPassword = 'password123Segurojaja';

        $users = [
            [
                'name' => 'Juan Alexander',
                'email' => 'alexdark921027@gmail.com',
                'role' => 'user',
            ]
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password'] ?? $defaultPassword),
                    'main_user_id'=> $mainUser->id ?? null,
                ]
            );

            $user->syncRoles($userData['role']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('main_user_id');
        });
        $userRepository = new UserRepository();
        $user = $userRepository->getUserByEmail("alexdark921027@gmail.com");
        if ($user) {
            $user->delete();
        }

    }
};
