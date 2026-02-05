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
            'list' => [
                'list.process',
                'list.preview',
                'list.validate',
                'list.view_all',
                'list.delete',
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
            ],
            'user' => [
                'list.process',
                'list.preview',
                'list.delete',
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
