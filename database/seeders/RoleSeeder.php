<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $permissionsByModule = [
            'dashboard' => [
                'dashboard.index',
            ],
            'user' => [
                'user.index'
            ],
            'list' => [
                'list.process',
                'list.preview',
                'list.validate',
                'list.view_all',
                'list.delete',
            ],
            'daily_number' => [
                'daily_number.index',
                'daily_number.show',
                'daily_number.create',
                'daily_number.edit',
                'daily_number.delete',
            ],
            'transaction' => [
                'transaction.index',
                'transaction.show',
                'transaction.create',
                'transaction.edit',
                'transaction.delete',
                'transaction.get_balance',
            ],
            'config_admin' => [
                'config_admin.index',
                'config_admin.show',
                'config_admin.create',
                'config_admin.edit',
                'config_admin.delete'
            ],

        ];
        $allPermissions = [];
        foreach ($permissionsByModule as $module => $perms) {
            foreach ($perms as $permissionName) {
                $allPermissions[] = Permission::firstOrCreate(['name' => $permissionName]);
            }
        }

        $roles = [
            'super-admin' => [
                'all' => true
            ],
            'admin' => [
                'list.validate',
                'list.preview',
                'list.view_all',
                'list.delete',
                'daily_number.index',
                'daily_number.show',
                'daily_number.create',
                'daily_number.edit',
                'daily_number.delete',
                'transaction.index',
                'transaction.show',
                'transaction.create',
                'transaction.edit',
                'transaction.delete',
                'transaction.get_balance',
                'config_admin.index',
                'config_admin.show',
                'config_admin.create',
                'config_admin.edit',
                'config_admin.delete'

            ],
            'user' => [
                'list.process',
                'list.preview',
                'list.delete',
                'transaction.get_balance'
            ],
        ];

        foreach ($roles as $roleName => $assignedPerms) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            if (isset($assignedPerms['all']) && $assignedPerms['all'] === true) {
                $role->syncPermissions(Permission::all());
            } else {
                $role->syncPermissions($assignedPerms);
            }
        }
    }
}
