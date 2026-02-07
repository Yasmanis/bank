<?php

namespace App\Dto\Transaction;

use App\Models\Transaction;

class TransactionResponseDto
{
    public function __construct(
        public int     $id,
        public string  $user_name,
        public float   $amount,
        public string  $type,        // income / outcome
        public string  $type_label,  // Recogida / Entrega
        public string  $status,      // pending / approved / rejected
        public string  $description,
        public string  $date,
        public string  $created_by_name,
        public ?string $actioned_by_name = null
    )
    {
    }

    public static function fromModel($model): self
    {
        return new self(
            id: $model->id,
            user_name: $model->user->name,
            amount: (float)$model->amount,
            type: $model->type,
            type_label: $model->type === 'income' ? 'Recogida' : 'Entrega',
            status: $model->status,
            description: $model->description,
            date: $model->date->format('d/m/Y'),
            created_by_name: $model->admin->name ?? 'Sistema',
            actioned_by_name: $model->actioner->name ?? null
        );
    }
}
