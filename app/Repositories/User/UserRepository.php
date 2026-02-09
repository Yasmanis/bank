<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\BaseRepository;
use App\Repositories\RepositoryInterface;

class UserRepository extends BaseRepository implements RepositoryInterface
{
    public function __construct()
    {
        parent::__construct(User::class);
    }

    public function getPaginated(array $filters, $perPage = 15)
    {
        return User::with('roles')
            ->whereHas('roles', function($query){
                    $query->where('name','user');
            })
            ->when($filters['user_id'] ?? null, fn($q, $id) => $q->where('id', $id))
            ->when($filters['name'] ?? null, fn($q, $s) => $q->where('name', $s))
            ->paginate($perPage);
    }

}
