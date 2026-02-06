<?php

namespace App\Dto\DailyNumber;
use App\Models\DailyNumber;

class DailyNumberPartialResponseDto
{
    public function __construct(
        public int $id,
        public string $fixed,
        public string $hundred,
        public string $runner1,
        public string $runner2,
        public string $hourly,
        public string $date
    ) {}

    public static function fromModel(DailyNumber $model): self
    {
        return new self(
            id: $model->id,
            fixed: $model->fixed,
            hundred: $model->hundred,
            runner1: $model->runner1,
            runner2: $model->runner2,
            hourly: $model->hourly,
            date: $model->date->format('d/m/Y')
        );
    }
}
