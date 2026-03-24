<?php

namespace App\Dto\BankList;

use App\Models\BankList;

class BankListFullResponseDto
{
    public function __construct(
        public int     $id,
        public string  $hourly,
        public string  $creator_name,
        public string  $created_at,
        public string  $status,
        public ?string  $text,
        public array   $processed_text,
        public int     $created_by,
        public ?int    $updated_by,
        public ?int    $approved_by,
        public ?string $bank_name,
        public ?array  $error_log,
        public ?string $file_url,
        public ?array $manual_results,
        public ?string $validated_by_name,
        public ?string $validated_at
    )
    {
    }

    /**
     * Mapea un modelo BankList a este DTO.
     */
    public static function fromModel($model): self
    {
        $data = $model->processed_text;
        if ($model->manual_results) {
            $date = $model->created_at->format('d/m/Y');
            $hourly = $model->hourly->value ?? $model->hourly;
            $manual = $model->manual_results;
            $data['total'] = (float)($manual['total'] ?? 0);
            $data['fixed'] = (int)($manual['fixed'] ?? 0);
            $data['hundred'] = (int)($manual['hundred'] ?? 0);
            $data['parlet'] = (int)($manual['parlet'] ?? 0);
            $data['triplet'] = (int)($manual['triplet'] ?? 0);
            $data['runner1'] = (int)($manual['runner1'] ?? 0);
            $data['runner2'] = (int)($manual['runner2'] ?? 0);

            if (isset($manual['prizes'])) {
                $data['prizes_preview'] = [
                    'found' => true,
                    'total_prizes' => (float)$manual['prizes'],
                    'breakdown' => [
                        'manual' => (float)$manual['prizes']
                    ],
                    'winning_number' => 'Validación Manual',
                    'draw_date' => $date,
                    'draw_hourly' => $hourly
                ];
            }
        }

        return new self(
            id: $model->id,
            hourly: $model->hourly->value ?? $model->hourly,
            creator_name: $model->user->name,
            created_at: $model->created_at->format('d/m/Y H:i'),
            status: $model->status ?? 'Pendiente',
            text: $model->text,
            processed_text: $data,
            created_by: (int)$model->created_by,
            updated_by: $model->updated_by ? (int)$model->updated_by : null,
            approved_by: $model->approved_by ? (int)$model->approved_by : null,
            bank_name: $model->bank->name ?? 'Sin asignar',
            error_log: $model->error_log,
            file_url: $model->file_path ? asset('storage/' . $model->file_path) : null,
            manual_results: $model->manual_results,
            validated_by_name: $model->validator->name ?? null,
            validated_at: $model->validated_at ? $model->validated_at->format('d/m/Y H:i') : null
        );
    }
}
