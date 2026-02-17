<?php

namespace App\Repositories\DailyNumber;

use App\Models\DailyNumber;
use App\Repositories\BaseRepository;
use App\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class DailyNumberRepository extends BaseRepository implements RepositoryInterface
{
    public function __construct()
    {
        parent::__construct(DailyNumber::class);
    }


    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['hourly'] ?? null, fn($q, $h) => $q->where('hourly', $h))
            ->when($filters['from'] ?? null, fn($q, $f) => $q->whereDate('date', '>=', $f))
            ->when($filters['to'] ?? null, fn($q, $t) => $q->whereDate('date', '<=', $t))
            ->latest('date')
            ->latest('hourly');
    }

}
