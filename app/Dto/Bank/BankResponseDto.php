<?php

namespace App\Dto\Bank;

class BankResponseDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public bool $is_active
    ) {}

    public static function fromModel($model): self
    {
        return new self(
            id: $model->id,
            name: $model->name,
            description: $model->description ?? '',
            is_active: (bool) $model->is_active
        );
    }

}
