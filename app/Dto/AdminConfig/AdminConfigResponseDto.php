<?php

namespace App\Dto\AdminConfig;

class AdminConfigResponseDto
{
    public function __construct(
        public int $id,
        public int $fixed,
        public int $hundred,
        public int $parlet,
        public int $runner1,
        public int $runner2,
        public int $triplet,
        public float $commission
    ) {}

    public static function fromModel($model): self
    {
        return new self(
            id: $model->id,
            fixed: $model->fixed,
            hundred: $model->hundred,
            parlet: $model->parlet,
            runner1: $model->runner1,
            runner2: $model->runner2,
            triplet: $model->triplet,
            commission: (float) $model->default_commission
        );
    }
}
