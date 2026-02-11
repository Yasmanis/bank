<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminConfigSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\AdminConfig::updateOrCreate(
            ['id' => 1], // Buscamos la configuración con ID 1
            [
                'user_id' => 1,
                "fixed" => 75,
                "hundred" => 300,
                "parlet" => 400,
                "runner1" => 25,
                "runner2" => 25,
                "triplet" => 70,
                "commission" => 25.00,
                "created_by" => 1,
            ]
        );

        $this->command->info('configuraciones procesados correctamente.');
    }
}
