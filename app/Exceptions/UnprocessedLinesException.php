<?php

namespace App\Exceptions;

use Exception;

class UnprocessedLinesException extends Exception
{
    protected array $lines;

    public function __construct(array $lines)
    {
        parent::__construct("Existen líneas que no pudieron ser procesadas.");
        $this->lines = $lines;
    }

    public function getLines(): array
    {
        return $this->lines;
    }
}
