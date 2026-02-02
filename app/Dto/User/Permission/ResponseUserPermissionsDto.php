<?php

namespace App\Dto\User\Permission;

class ResponseUserPermissionsDto
{
    public array $permissions = [];

    public function __construct($permissionsCollection)
    {
        $this->permissions = $permissionsCollection->map(function ($permission) {
            return $permission->name;
        })->toArray();
    }

}
