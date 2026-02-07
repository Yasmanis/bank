<?php

namespace App\Repositories\AdminConfig;

use App\Models\AdminConfig;
use App\Repositories\BaseRepository;
use App\Repositories\RepositoryInterface;

class AdminConfigRepository extends BaseRepository implements RepositoryInterface
{
    public function __construct()
    {
        parent::__construct(AdminConfig::class);
    }

    public function getByUserId(int $userId)
    {
        return AdminConfig::where('user_id', $userId)->first();
    }
}
