<?php

namespace App\Dto\BankList;

use App\Models\BankList;
use Carbon\Carbon;

class BankListPartialResponseDto
{
    public function __construct(
        public int $id,
        public string $hourly,
        public string $creator_name,
        public string $created_at,
        public string $status
    ) {}

    /**
     * Mapea un modelo BankList a este DTO.
     */
    public static function fromModel(BankList $model): self
    {
        return new self(
            id: $model->id,
            hourly: $model->hourly,
            creator_name: $model->user->name,
            created_at: $model->created_at->format('d/m/Y H:i'),
            status: $model->status
        );
    }
}
