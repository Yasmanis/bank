<?php

namespace App\Http\Controllers;

use App\Dto\User\Permission\ResponseUserPermissionsDto;

class UserController extends Controller
{
    public function userPermissions()
    {
        $user = auth()->user();
        $permissionsCollection = $user->getPermissionsViaRoles();
        $userPermissionsResponseDto = new ResponseUserPermissionsDto($permissionsCollection);
        return response()->json($userPermissionsResponseDto);
    }

}
