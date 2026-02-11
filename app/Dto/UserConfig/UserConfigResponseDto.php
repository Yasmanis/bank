<?php

namespace App\Dto\UserConfig;

class UserConfigResponseDto
{
    public function __construct(
        public int $id,
        public int $user_id,
        public string $user_name,
        public int $fixed,
        public int $hundred,
        public int $parlet,
        public int $triplet,
        public int $runner1,
        public int $runner2,
        public float $commission
    ) {}

    public static function fromModel($model): self
    {
        return new self(
            id: $model->id,
            user_id: $model->user_id,
            user_name: $model->user->name,
            fixed: $model->fixed,
            hundred: $model->hundred,
            parlet: $model->parlet,
            triplet: $model->triplet,
            runner1: $model->runner1,
            runner2: $model->runner2,
            commission: (float) $model->commission
        );
    }
}
