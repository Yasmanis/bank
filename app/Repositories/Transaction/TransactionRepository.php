<?php

namespace App\Repositories\Transaction;

use App\Models\Transaction;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

class TransactionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(Transaction::class);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query->with(['user', 'admin', 'actioner', 'bank'])
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id))
            ->when($filters['bank_id'] ?? null, fn($q, $id) => $q->where('bank_id', $id))
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($filters['type'] ?? null, fn($q, $t) => $q->where('type', $t))
            ->when($filters['from'] ?? null, fn($q, $f) => $q->whereDate('date', '>=', $f))
            ->when($filters['to'] ?? null, fn($q, $t) => $q->whereDate('date', '<=', $t));
    }

    public function getUserBalanceByBank($userId, $bankId): float
    {
        return (float)Transaction::where('user_id', $userId)
            ->where('bank_id', $bankId)
            ->where('status', 'approved')
            ->selectRaw("SUM(CASE WHEN type = 'outcome' THEN amount ELSE -amount END) as balance")
            ->value('balance') ?? 0.0;
    }
}
