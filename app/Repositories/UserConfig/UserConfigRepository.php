<?php

namespace App\Repositories\UserConfig;

use App\Models\UserConfig;
use App\Repositories\BaseRepository;

class UserConfigRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(UserConfig::class);
    }

    public function findByUserId(int $userId)
    {
        return $this->query()->where('user_id', $userId)->firstOrFail();
    }
}
