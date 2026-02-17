<?php

namespace App\Http\Controllers;

use App\Dto\Bank\BankResponseDto;
use App\Http\Requests\BankIndexRequest;
use App\Http\Requests\BankStoreRequest;
use App\Repositories\Bank\BankRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

/**
 * @group Gestión de Bancos
 * APIs para que el Admin maneje sus entidades financieras (Bancos).
 */
class BankController extends Controller
{
    protected $repository;

    public function __construct(BankRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Listado de bancos
     */
    public function index(BankIndexRequest $request)
    {
        try {
            $filters = $request->validated();
            $perPage = $request->integer('per_page', 15);
            $paginator = $this->repository->getPaginated($filters, $perPage);
            $paginator->appends($request->query());
            $paginator->through(fn($model) => BankResponseDto::fromModel($model));
            return $this->successPaginated($paginator);

        } catch (\Throwable $th) {
            return $this->error('Error al obtener datos', 500, $th->getMessage());
        }
    }

    /**
     * Crear un nuevo banco.
     */
    public function store(BankStoreRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $data = array_merge($request->validated(), [
                'admin_id' => auth()->id()
            ]);
            $bank = $this->repository->store($data);
            return $this->success(BankResponseDto::fromModel($bank), 'Banco creado con éxito');
        } catch (\Throwable $th) {
            return $this->error('No se pudo crear el banco', 422, $th->getMessage());
        }
    }

    /**
     * Ver detalle de un banco.
     * @throws AuthorizationException
     */
    public function show($id): \Illuminate\Http\JsonResponse
    {
        $bank = $this->repository->getModelById($id);
        $this->authorize('view', $bank);
        return $this->success(BankResponseDto::fromModel($bank));
    }

    /**
     * Actualizar datos del banco.
     * @throws AuthorizationException
     */
    public function update(BankStoreRequest $request, $id): \Illuminate\Http\JsonResponse
    {
        $bank = $this->repository->getModelById($id);
        $this->authorize('update', $bank);

        $this->repository->update($request->validated(), $id);

        return $this->success(null, 'Banco actualizado');
    }

    /**
     * Eliminar (Desactivar) un banco.
     * @throws AuthorizationException
     */
    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $bank = $this->repository->getModelById($id);
        $this->authorize('delete', $bank);
        $this->repository->delete($id);
        return $this->success(null, 'Banco eliminado correctamente');
    }
}
