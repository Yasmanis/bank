<?php

namespace App\Repositories\BankList;

use App\Models\BankList;

class BankListRepository
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

    public function getPaginatedByUser(int|string|null $userId, mixed $perPage = 15)
    {
        $query = $this->model->with('user')->latest();
        if (!auth()->user()->hasPermissionTo('list.view_all')) {
            $query->where('user_id', $userId);
        }
        return $query->paginate($perPage);
    }

}
