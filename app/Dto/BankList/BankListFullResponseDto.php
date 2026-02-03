<?php

namespace App\Dto\BankList;

use App\Models\BankList;

class BankListFullResponseDto
{
    public function __construct(
        public int $id,
        public string $hourly,
        public string $creator_name,
        public string $created_at,
        public string $status,
        public string $text,
        public array $processed_text,
        public int $created_by,
        public ?int $updated_by, // Nulable por si no se ha editado
        public ?int $approved_by, // Nulable por si no se ha aprobado
    ) {}

    /**
     * Mapea un modelo BankList a este DTO.
     */
    public static function fromModel(BankList $model): self
    {
        return new self(
            id: $model->id,
            hourly: strtoupper($model->hourly->value ?? $model->hourly),
            creator_name: $model->user->name, // Requiere que 'user' esté cargado
            created_at: $model->created_at->format('d/m/Y H:i'),
            status: $model->status ?? 'Pendiente',
            text: $model->text,
            processed_text: $model->processed_text, // Laravel ya lo castea a array
            created_by: (int) $model->created_by,
            updated_by: $model->updated_by ? (int) $model->updated_by : null,
            approved_by: $model->approved_by ? (int) $model->approved_by : null,
        );
    }
}
