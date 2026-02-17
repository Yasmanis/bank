<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\User;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::role('super-admin')->first() ?? User::first();
        if (!$admin) {
            $this->command->error('No se encontró un usuario para asociar los bancos. Ejecuta primero el UserSeeder.');
            return;
        }
        // 2. Definimos los bancos iniciales
        $banks = [
            [
                'name' => 'Banco Principal',
                'description' => 'Caja central de operaciones',
                'is_active' => true,
            ]
        ];

        // 3. Procesamos la creación/actualización
        foreach ($banks as $bankData) {
            Bank::updateOrCreate(
                ['name' => $bankData['name'], 'admin_id' => $admin->id],
                [
                    'description' => $bankData['description'],
                    'is_active' => $bankData['is_active'],
                ]
            );
        }

        $this->command->info('Bancos creados/actualizados correctamente.');
    }
}
