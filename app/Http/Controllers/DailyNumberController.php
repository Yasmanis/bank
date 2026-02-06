<?php

namespace App\Http\Controllers;

use App\Dto\DailyNumber\DailyNumberPartialResponseDto;
use App\Http\Requests\DailyNumberIndexRequest;
use App\Http\Requests\DailyNumberStoreRequest;
use App\Http\Requests\DailyNumberUpdateRequest;
use App\Repositories\DailyNumber\DailyNumberRepository;
use Illuminate\Http\Request;

class DailyNumberController extends Controller
{
    protected $repository;

    public function __construct()
    {
        $this->repository = new DailyNumberRepository();
    }

    public function index(DailyNumberIndexRequest $request)
    {
        try {
            $filters = $request->validated();
            $perPage = $request->get('per_page', 15);
            $paginator = $this->repository->getPaginated($filters, $perPage);
            $paginator->appends($request->query());
            $paginator->through(fn ($model) => DailyNumberPartialResponseDto::fromModel($model));
            return $this->successPaginated($paginator);
        } catch (\Throwable $th) {
            return $this->error('Error al obtener los números diarios', 422, $th->getMessage());
        }
    }

    public function store(DailyNumberStoreRequest $request)
    {
        try {
            $model = $this->repository->store($request->validated());
            return $this->success([
                'id' => $model->id
            ], 'Número diario registrado');
        } catch (\Exception $e) {
            return $this->error('Error al guardar', 422, $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            return $this->success($this->repository->getModelById($id));
        } catch (\Exception $e) {
            return $this->error('No encontrado', 404);
        }
    }

    /**
     * Actualizar un número diario existente
     */
    public function update(DailyNumberUpdateRequest $request, $id)
    {
        try {
            $this->repository->update($request->validated(), $id);
            $model = $this->repository->getModelById($id);
            return $this->success(
                ['id' => $model->id],
                'Número diario actualizado correctamente'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('El registro no existe', 404);
        } catch (\Throwable $th) {
            return $this->error('No se pudo actualizar el registro', 422, $th->getMessage());
        }
    }

    public function destroy($id)
    {
        return $this->success($this->repository->delete($id));

    }
}
