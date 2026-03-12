<?php

namespace App\Dto\BankList;

use App\Models\BankList;

class BankListPartialResponseDto
{
    public function __construct(
        public int $id,
        public string $hourly,
        public string $creator_name,
        public string $created_at,
        public string $status,
        public string $created_at_raw,
        public float $total,
        public ?string $bank_name,
        public ?array $error_log,
        public ?string $file_url,
    ) {}

    /**
     * Mapea un modelo BankList a este DTO.
     */
    public static function fromModel(BankList $model): self
    {
        return new self(
            id: $model->id,
            hourly: strtoupper($model->hourly),
            creator_name: $model->user->name,
            created_at: $model->created_at->format('d/m/Y H:i'),
            status: $model->status,
            created_at_raw: $model->created_at->format('Y-m-d'), // Simplificado para el groupBy del controller
            total: (float) ($model->processed_text['total'] ?? 0),
            bank_name: $model->bank->name ?? 'Sin asignar',
            error_log: $model->error_log,
            file_url: $model->file_path ? asset('storage/' . $model->file_path) : null,
        );
    }
}
