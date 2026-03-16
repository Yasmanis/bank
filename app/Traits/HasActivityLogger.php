<?php

namespace App\Traits;

use App\Services\ActivityLoggerService;

trait HasActivityLogger
{
    protected function logger(): ActivityLoggerService
    {
        return app(ActivityLoggerService::class);
    }
}
