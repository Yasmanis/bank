<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\BaseRepository;
use App\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class UserRepository extends BaseRepository implements RepositoryInterface
{
    public function __construct()
    {
        parent::__construct(User::class);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query->with(['roles'])
            ->whereHas('roles', function ($query) {
                $query->where('name', 'user');
            })
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('id', $id))
            ->when($filters['name'] ?? null, fn($q, $s) => $q->where('name', $s));
    }

    public function getUserByEmail(string $email)
    {
        return $this->query()->where('email', $email)->first();
    }

}
