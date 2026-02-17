<?php

namespace App\Http\Controllers;

use App\Dto\Transaction\TransactionResponseDto;
use App\Http\Requests\TransactionIndexRequest;
use App\Http\Requests\TransactionStoreRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Models\Transaction;
use App\Repositories\Transaction\TransactionRepository;
use App\Repositories\User\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    protected TransactionRepository $repository;

    public function __construct(TransactionRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(TransactionIndexRequest $request)
    {
        $filters = $request->validated();
        $perPage = $request->get('per_page', 15);
        $paginator = $this->repository->getPaginated($filters, $perPage);
        $paginator->through(fn($model) => TransactionResponseDto::fromModel($model));
        return $this->successPaginated($paginator);
    }


    public function store(TransactionStoreRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = array_merge($request->validated(), [
                'status' => Transaction::STATUS_APPROVED
            ]);
            $transaction = $this->repository->store($data);
            DB::commit();
            return $this->success([
                'id' => $transaction->id,
                'new_balance' => $this->repository->getUserBalanceByBank($request->user_id, $request->bank_id)
            ], 'Transacción registrada correctamente');

        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error('Error al registrar transacción', 422, $th->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $model = $this->repository->getModelById($id);
            $model->load(['user', 'admin', 'actioner']);
            return $this->success(TransactionResponseDto::fromModel($model));
        } catch (\Throwable $th) {
            return $this->error('Transacción no encontrada', 404);
        }
    }

    public function update(TransactionUpdateRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            $this->repository->update($request->validated(), $id);
            $model = $this->repository->getModelById($id);
            DB::commit();
            return $this->success([
                'id' => $model->id,
                'new_balance' => $this->repository->getUserBalanceByBank($model->user_id, $model->bank_id)
            ], 'Transacción actualizada correctamente');

        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error('No se pudo actualizar la transacción', 422, $th->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $model = $this->repository->getModelById($id);
            $userId = $model->user_id;
            $this->repository->delete($id);
            return $this->success([
                'new_balance' => $this->repository->getUserBalance($userId)
            ], 'Transacción eliminada correctamente');
        } catch (\Throwable $th) {
            return $this->error('Error al eliminar la transacción', 422, $th->getMessage());
        }
    }

    public function getBalanceByUser($id)
    {
        try {
            $userRepository = new UserRepository();
            $user = $userRepository->getModelById($id);
            return $this->success([
                'balance' => $this->repository->getUserBalance($user->id)
            ]);
        } catch (\Throwable $th) {
            return $this->error('Error al obtener el Balance', 422, $th->getMessage());
        }
    }

}
