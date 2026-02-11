<?php

namespace App\Http\Controllers;

use App\Dto\UserConfig\UserConfigResponseDto;
use App\Http\Requests\UserConfigStoreRequest;
use App\Http\Requests\UserConfigUpdateRequest;
use App\Repositories\UserConfig\UserConfigRepository;

/**
 * @group Configuración de Usuarios
 * APIs para gestionar las tarifas personalizadas de cada listero.
 */
class UserConfigController extends Controller
{
    protected $repository;

    public function __construct(UserConfigRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Listado de configuraciones personalizadas.
     */
    public function index()
    {
        $configs = \App\Models\UserConfig::with('user')->paginate();
        $configs->through(fn($m) => UserConfigResponseDto::fromModel($m));
        return $this->successPaginated($configs);
    }

    /**
     * Asignar tarifas a un usuario.
     */
    public function store(UserConfigStoreRequest $request)
    {
        try {
            $config = $this->repository->store($request->validated());
            return $this->success(UserConfigResponseDto::fromModel($config), 'Configuración creada');
        } catch (\Throwable $th) {
            return $this->error('No se pudo crear', 422, $th->getMessage());
        }
    }

    /**
     * Obtener tarifas específicas por ID de registro.
     */
    public function show($id)
    {
        try {
            $config = $this->repository->getModelById($id);
            $this->authorize('view', $config);
            return $this->success(UserConfigResponseDto::fromModel($config->load('user')));
        } catch (\Throwable $th) {
            return $this->error('Configuración no encontrada', 404);
        }
    }

    /**
     * Obtener tarifas específicas por ID de usuario.
     */
    public function getByUserId($userId)
    {
        try {
            $config = $this->repository->findByUserId($userId);
            if (!$config) return $this->error('El usuario no tiene configuración propia', 404);

            $this->authorize('view', $config);
            return $this->success(UserConfigResponseDto::fromModel($config->load('user')));
        } catch (\Throwable $th) {
            return $this->error('Error al buscar configuración', 422);
        }
    }

    /**
     * Actualizar configuración de usuario.
     *
     * Permite actualizar uno o varios campos de la tarifa.
     */
    public function update(UserConfigUpdateRequest $request, $id)
    {
        try {
            $config = $this->repository->getModelById($id);
            $this->authorize('update', $config);

            $this->repository->update($request->validated(), $id);
            $updated = $this->repository->getModelById($id);

            return $this->success(
                UserConfigResponseDto::fromModel($updated->load('user')),
                'Configuración actualizada correctamente'
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->error('No tienes permiso para editar esta configuración', 403);
        } catch (\Throwable $th) {
            return $this->error('Error al actualizar', 422, $th->getMessage());
        }
    }

    /**
     * Eliminar configuración de usuario.
     *
     * Al eliminarla, el usuario volverá a usar las tarifas globales del administrador.
     */
    public function destroy($id)
    {
        try {
            $config = $this->repository->getModelById($id);
            $this->authorize('delete', $config);

            $this->repository->delete($id);

            return $this->success(null, 'Configuración eliminada. El usuario ahora usará las tarifas generales.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->error('No tienes permiso para eliminar esto', 403);
        } catch (\Throwable $th) {
            return $this->error('Error al eliminar', 422, $th->getMessage());
        }
    }


}
