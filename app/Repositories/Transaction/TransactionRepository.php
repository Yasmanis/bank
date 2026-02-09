<?php

namespace App\Repositories\Transaction;

use App\Models\Transaction;
use App\Repositories\BaseRepository;

class TransactionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Transaction::class);
    }

    public function getPaginated(array $filters, $perPage = 15)
    {
        return Transaction::with(['user', 'admin', 'actioner'])
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($filters['type'] ?? null, fn($q, $t) => $q->where('type', $t))
            ->when($filters['from'] ?? null, fn($q, $f) => $q->whereDate('date', '>=', $f))
            ->when($filters['to'] ?? null, fn($q, $t) => $q->whereDate('date', '<=', $t))
            ->latest('date')
            ->paginate($perPage);
    }

    public function getUserBalance($userId): float
    {
        return (float) Transaction::where('user_id', $userId)
            ->where('status', 'approved')
            ->selectRaw("SUM(CASE WHEN type = 'outcome' THEN amount ELSE -amount END) as balance")
            ->value('balance') ?? 0.0;
    }
}
