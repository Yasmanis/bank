<?php

namespace App\Dto\Settlement;

class SettlementResultDto
{
    public function __construct(
        public int $user_id,
        public string $user_name,
        public string $date,
        public string $hourly,
        public float $total_sales,      // Venta Bruta
        public float $commission_amt,   // Monto descontado por comisión
        public float $net_sales,        // Venta Bruta - Comisión
        public float $total_prizes,     // Total de premios a pagar
        public float $final_balance,    // Saldo final (Neto - Premios)
        public array $prizes_breakdown, // Detalle de qué ganó cada cosa
        public array $applied_rates     // Precios usados (Snapshot)
    ) {}
}
