<?php

namespace App\Repositories\Bank;

use App\Models\Bank;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

class BankRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Bank::class);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->when(isset($filters['is_active']), function ($q) use ($filters) {
                $q->where('is_active', $filters['is_active']);
            });
    }

}
