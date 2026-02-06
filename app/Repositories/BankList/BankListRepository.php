<?php

namespace App\Repositories\BankList;

use App\Models\BankList;
use App\Repositories\RepositoryInterface;

class BankListRepository implements RepositoryInterface
{
    protected \Illuminate\Database\Eloquent\Builder $model;

    public function __construct()
    {
        $this->model = BankList::query();
    }

    public function getModelById($id): BankList|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection|null
    {
        return $this->model->findOrFail($id);
    }

    public function store(array $data): BankList|\Illuminate\Database\Eloquent\Model
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id): int
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function getPaginated(array $filters, int|string|null $userId, $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = BankList::with('user');
        if (!auth()->user()->can('list.view_all')) {
            $query->where('user_id', $userId);
        }

        $query->when($filters['hourly'] ?? null, function ($q, $hourly) {
            $q->where('hourly', $hourly);
        })
            ->when($filters['status'] ?? null, function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($filters['from'] ?? null, function ($q, $from) {
                $q->whereDate('created_at', '>=', $from);
            })
            ->when($filters['to'] ?? null, function ($q, $to) {
                $q->whereDate('created_at', '<=', $to);
            });

        return $query->orderBy('created_at', 'desc')
            ->orderBy('user_id', 'asc')
            ->paginate($perPage);
    }

    public function delete($id): int
    {
        return $this->model->findOrFail($id)->delete();
    }

}
