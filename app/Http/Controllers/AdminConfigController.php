<?php

namespace App\Http\Controllers;

use App\Dto\AdminConfig\AdminConfigResponseDto;
use App\Http\Requests\AdminConfigStoreRequest;
use App\Http\Requests\AdminConfigUpdateRequest;
use App\Repositories\AdminConfig\AdminConfigRepository;
use Illuminate\Http\Request;
class AdminConfigController extends Controller
{
    protected AdminConfigRepository $repository;

    public function __construct(AdminConfigRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtener la configuración del admin autenticado
     */
    public function index(Request $request)
    {
        $userId = $request->user_id ?? auth()->id();
        $config = $this->repository->getByUserId($userId);
        if (!$config) {
            return $this->error('No tienes una configuración establecida', 404);
        }
        $this->authorize('view', $config);
        return $this->success(AdminConfigResponseDto::fromModel($config));
    }


    public function store(AdminConfigStoreRequest $request)
    {
        try {
            $config = $this->repository->store($request->validated());
            return $this->success(
                AdminConfigResponseDto::fromModel($config),
                'Configuración creada correctamente'
            );
        } catch (\Throwable $th) {
            return $this->error('Error al guardar la configuración', 422, $th->getMessage());
        }
    }


    public function show($id)
    {
        try {
            $model = $this->repository->getModelById($id);
            $this->authorize('view', $model);
            return $this->success(AdminConfigResponseDto::fromModel($model));
        } catch (\Throwable $th) {
            return $this->error('Configuración no encontrada', 404);
        }
    }

    public function update(AdminConfigUpdateRequest $request, $id)
    {
        try {
            $model = $this->repository->getModelById($id);
            $this->authorize('view', $model);
            $this->repository->update($request->validated(), $id);
            $updatedConfig = $this->repository->getModelById($id);
            return $this->success(
                AdminConfigResponseDto::fromModel($updatedConfig),
                'Configuración actualizada correctamente'
            );
        } catch (\Throwable $th) {
            return $this->error('Error al actualizar la configuración', 422, $th->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $config = $this->repository->getModelById($id);
            $this->authorize('delete', $config);
            $this->repository->delete($id);
            return $this->success(null, 'Configuración eliminada correctamente');
        } catch (\Throwable $th) {
            return $this->error('Error al intentar eliminar', 422, $th->getMessage());
        }
    }


}
