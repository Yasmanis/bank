<?php

namespace App\Http\Controllers;

use App\Dto\User\Permission\ResponseUserPermissionsDto;
use App\Dto\User\UserIndexResponseDto;
use App\Http\Requests\UserIndexRequest;
use App\Repositories\User\UserRepository;

class UserController extends Controller
{

    protected UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(UserIndexRequest $request)
    {
        $filters = $request->validated();
        $perPage = $request->get('per_page', 15);
        $paginator = $this->repository->getPaginated($filters, $perPage);
        $paginator->through(fn($model) => UserIndexResponseDto::fromModel($model));
        return $this->successPaginated($paginator);

    }

    public function userPermissions()
    {
        $user = auth()->user();
        $permissionsCollection = $user->getPermissionsViaRoles();
        $userPermissionsResponseDto = new ResponseUserPermissionsDto($permissionsCollection);
        return response()->json($userPermissionsResponseDto);
    }

}
