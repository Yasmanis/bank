<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('super-admin'),
        ]);
        $superadmin->assignRole('super-admin');

        $admin = User::create([
            'name' => 'Admin Prueba',
            'email' => 'admin@test.com',
            'password' => Hash::make('admin'),
        ]);
        $admin->assignRole('admin');

        $user = User::create([
            'name' => 'Usuario',
            'email' => 'user@test.com',
            'password' => Hash::make('user'),
        ]);
        $user->assignRole('user');
    }
}
