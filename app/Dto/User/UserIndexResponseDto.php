<?php

namespace App\Dto\User;

use App\Models\Transaction;

class UserIndexResponseDto
{
    public function __construct(
        public int     $id,
        public string  $user_name,
    )
    {
    }

    public static function fromModel($model): self
    {
        return new self(
            id: $model->id,
            user_name: $model->name
        );
    }
}
