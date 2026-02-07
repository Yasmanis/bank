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

}
