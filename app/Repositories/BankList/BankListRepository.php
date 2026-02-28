<?php

namespace App\Repositories\BankList;

use App\Models\BankList;
use App\Repositories\BaseRepository;
use App\Repositories\RepositoryInterface;

class BankListRepository extends BaseRepository implements RepositoryInterface
{
    public function __construct()
    {
        parent::__construct(BankList::class);
    }
    public function getPaginatedByUser(array $filters, int $authId, $perPage = 15)
    {
        $query = BankList::with('user');
        if (!auth()->user()->can('list.view_all')) {
            $query->where('user_id', $authId);
        } else {
            $query->when($filters['user_id'] ?? null, function ($q, $userId) {
                $q->where('user_id', $userId);
            });
            $query->when($filters['name'] ?? null, function ($q, $name) {
                $q->whereHas('user', function ($u) use ($name) {
                    $u->where('name', 'like', "%{$name}%");
                });
            });
        }

        $query->when($filters['hourly'] ?? null, fn($q, $h) => $q->where('hourly', $h))
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn($q, $f) => $q->whereDate('created_at', '>=', $f))
            ->when($filters['to'] ?? null, fn($q, $t) => $q->whereDate('created_at', '<=', $t));

        return $query->latest()->paginate($perPage);
    }

}
