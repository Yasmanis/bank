<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // 1. Crear Permisos
        Permission::create(['name' => 'dashboard.index']);
        //list
        Permission::create(['name' => 'list.process']);
        Permission::create(['name' => 'list.preview']);
        Permission::create(['name' => 'list.validate']);
        Permission::create(['name' => 'list.view_all']);
        Permission::create(['name' => 'list.delete']);

        // 2. Crear Roles y asignar permisos
        $superadmin = Role::create(['name' => 'super-admin']);
        $superadmin->givePermissionTo(Permission::all());

        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo([
            'list.validate',
            'list.preview',
            'list.view_all',
            'list.delete'
        ]);

        $user = Role::create(['name' => 'user']);
        $user->givePermissionTo([
            'list.process',
            'list.preview',
            'list.delete'
        ]);
    }
}
