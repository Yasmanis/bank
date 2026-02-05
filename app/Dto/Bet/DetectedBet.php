<?php

namespace App\Dto\Bet;

class DetectedBet
{
    public function __construct(
        public string $type,      // 'fixed', 'parlet', 'terminal', 'range', 'hundred'
        public string $number,    // El número apostado (ej: '05', '33x88', '123')
        public int $amount,       // Monto principal (Fijo)
        public int $runner1 = 0,  // Monto Corrida 1
        public int $runner2 = 0,  // Monto Corrida 2
        public ?string $originalLine = null, // Para debug o errores,
         public ?string $label = null
    ) {}
}
