<?php

namespace App\Repositories\DailyNumber;

use App\Models\DailyNumber;
use App\Repositories\BaseRepository;
use App\Repositories\RepositoryInterface;

class DailyNumberRepository extends BaseRepository implements RepositoryInterface
{
    public function __construct()
    {
        parent::__construct(DailyNumber::class);
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
