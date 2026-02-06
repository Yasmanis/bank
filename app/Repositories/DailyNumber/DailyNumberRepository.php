<?php

namespace App\Repositories\DailyNumber;

use App\Models\DailyNumber;
use App\Repositories\RepositoryInterface;

class DailyNumberRepository implements RepositoryInterface
{
    protected \Illuminate\Database\Eloquent\Builder $model;
    public function __construct()
    {
        $this->model = DailyNumber::query();
    }

    public function getModelById($id): DailyNumber
    {
        return $this->model->findOrFail($id);
    }

    public function store(array $data): DailyNumber
    {
        return $this->model->create($data);
    }

    public function update(array $data,$id): bool
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id): int
    {
        return $this->model->findOrFail($id)->delete();
    }

    public function getPaginated(array $filters, $perPage = 15)
    {
        return DailyNumber::query()
            ->when($filters['hourly'] ?? null, fn($q, $h) => $q->where('hourly', $h))
            ->when($filters['from'] ?? null, fn($q, $f) => $q->whereDate('date', '>=', $f))
            ->when($filters['to'] ?? null, fn($q, $t) => $q->whereDate('date', '<=', $t))
            ->latest('date')
            ->latest('hourly')
            ->paginate($perPage);
    }


}
