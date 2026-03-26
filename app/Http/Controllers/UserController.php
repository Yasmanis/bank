<?php

namespace App\Http\Controllers;

use App\Dto\User\Permission\ResponseUserPermissionsDto;
use App\Dto\User\UserIndexResponseDto;
use App\Http\Requests\UserIndexRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Repositories\User\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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


    /**
     * Crear un nuevo usuario.
     * Solo accesible por Super Admin.
     */
    public function store(UserStoreRequest $request)
    {
        try {
            $this->authorize('create', \App\Models\User::class);
            $data = $request->validated();

            $user = DB::transaction(function () use ($data) {
                $data['password'] = Hash::make($data['password']);
                $newUser = $this->repository->store($data);
                $newUser->assignRole($data['role']);
                return $newUser;
            });

            return $this->success(
                UserIndexResponseDto::fromModel($user),
                'Usuario creado exitosamente',
                201
            );

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->error('No tienes permisos para crear usuarios', 403);
        } catch (\Throwable $th) {
            return $this->error('Error al crear usuario', 422, $th->getMessage());
        }
    }


    /**
     * Actualizar un usuario existente.
     */
    public function update(UserUpdateRequest $request, $id)
    {
        try {
            $user = $this->repository->getModelById($id);
            $this->authorize('update', $user);
            $data = $request->validated();
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
            $this->repository->update($data, $id);

            $updatedUser = $this->repository->getModelById($id);
            return $this->success(
                UserIndexResponseDto::fromModel($updatedUser),
                'Usuario actualizado correctamente'
            );

        } catch (\Throwable $th) {
            return $this->error('No se pudo actualizar el usuario', 422, $th->getMessage());
        }
    }



    /**
     * @group Gestión de Usuarios
     *
     * Obtener los listeros asociados al usuario autenticado.
     */
    public function myListMakers(\Illuminate\Http\Request $request)
    {
        try {
            $filters = $request->only(['name']);
            $perPage = $request->integer('per_page', 15);
            // 2. Obtenemos los listeros (hijos) del usuario logueado
            $paginator = $this->repository->getSubUsersPaginated(
                auth()->id(),
                $filters,
                $perPage
            );
            $paginator->through(fn($model) => UserIndexResponseDto::fromModel($model));
            return $this->successPaginated($paginator);

        } catch (\Throwable $th) {
            return $this->error('Error al obtener tus listeros', 422, $th->getMessage());
        }
    }

    public function userPermissions()
    {
        $user = auth()->user();
        $permissionsCollection = $user->getPermissionsViaRoles();
        $userPermissionsResponseDto = new ResponseUserPermissionsDto($permissionsCollection);
        return response()->json($userPermissionsResponseDto);
    }

}
