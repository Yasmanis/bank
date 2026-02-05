<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = 'password123Segurojaja';

        $users = [
            [
                'name'  => 'Super Admin',
                'email' => 'superadmin@test.com',
                'role'  => 'super-admin',
                'password' => 'super-admin'
            ],
            [
                'name'  => 'Admin Prueba',
                'email' => 'admin@test.com',
                'role'  => 'admin',
            ],
            [
                'name' => 'Usuario',
                'email' => 'user@test.com',
                'password' => 'user',
                'role' => 'user'
            ],
            [
                'name' => 'Usuario2',
                'email' => 'user2@test.com',
                'password' => 'user2',
                'role' => 'user'
            ],
            [
                'name'  => 'Carlos Test',
                'email' => 'carlos@test.com',
                'role'  => 'user',
            ],
            [
                'name'  => 'Yurislier Test',
                'email' => 'yurislier@test.com',
                'role'  => 'user',
            ],
            [
                'name'  => 'Jose Test',
                'email' => 'jose@test.com',
                'role'  => 'user',
            ],
            [
                'name'  => 'Luis Test',
                'email' => 'luis@test.com',
                'role'  => 'user',
            ],
            [
                'name'  => 'Yasmanis Test',
                'email' => 'yasmanis@test.com',
                'role'  => 'user',
            ]
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name'     => $userData['name'],
                    'password' => Hash::make($userData['password'] ?? $defaultPassword),
                ]
            );

            $user->syncRoles($userData['role']);
        }

        $this->command->info('Usuarios procesados correctamente.');
    }
}
