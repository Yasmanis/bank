<?php

namespace App\Contracts;

interface LotteryScraperInterface
{
    /** @return array|null ['hundred' => '1', 'fixed' => '50', 'r1' => '20', 'r2' => '30'] */
    public function parse(string $hourly): ?array;
}
