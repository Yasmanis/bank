<?php

namespace App\Services;

use App\Models\Settlement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\DailyNumber;
use App\Models\BankList;
use App\Dto\Settlement\SettlementResultDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    /**
     * Calcula la liquidación filtrando por BANCO.
     */
    public function calculate(int $userId, int $bankId, string $date, string $hourly): SettlementResultDto
    {
        $user = User::findOrFail($userId);
        $rates = $user->getEffectiveRates(); // La lógica de prioridad se mantiene

        $win = DailyNumber::whereDate('date', $date)->where('hourly', $hourly)->first();
        if (!$win) throw new \Exception("Falta el número ganador para este turno.");

        // FILTRO POR BANCO: Solo obtenemos las listas enviadas a este banco específico
        $lists = BankList::where('user_id', $userId)
            ->where('bank_id', $bankId)
            ->whereDate('created_at', $date)
            ->where('hourly', $hourly)
            ->get();

        if ($lists->isEmpty()) throw new \Exception("No hay ventas registradas para este usuario en este banco.");

        $totalSales = $lists->sum(fn($l) => $l->processed_text['total'] ?? 0);
        $prizesData = $this->calculateTotalPrizes($lists, $win, $rates);

        $commissionAmt = $totalSales * ($rates['commission'] / 100);
        $netSales = $totalSales - $commissionAmt;
        $finalBalance = $netSales - $prizesData['total'];

        return new SettlementResultDto(
            user_id: $user->id,
            bank_id: $bankId,
            user_name: $user->name,
            date: $date,
            hourly: $hourly,
            total_sales: (float) $totalSales,
            commission_amt: (float) $commissionAmt,
            net_sales: (float) $netSales,
            total_prizes: (float) $prizesData['total'],
            final_balance: (float) $finalBalance,
            prizes_breakdown: $prizesData['breakdown'],
            applied_rates: $rates
        );
    }

    private function calculateTotalPrizes(Collection $lists, DailyNumber $win, array $rates): array
    {
        $total = 0;
        $breakdown = ['fixed' => 0, 'hundred' => 0, 'parlet' => 0, 'triplet' => 0, 'runners' => 0];

        // Definimos los números ganadores para comparar
        $winF = $win->fixed;
        $winH = $win->hundred . $win->fixed; // Centena completa (ej: 1 + 50 = 150)
        $winR1 = $win->runner1;
        $winR2 = $win->runner2;

        // El pool de ganadores para Parlets y Tripletas
        $winPool = [$winF, $winR1, $winR2];

        foreach ($lists as $list) {
            $bets = $list->processed_text['bets'] ?? [];

            foreach ($bets as $bet) {
                // 1. Verificar Fijos (fixed)
                if ($bet['type'] === 'fixed' && $bet['number'] === $winF) {
                    $gain = $bet['amount'] * $rates['fixed'];
                    $total += $gain;
                    $breakdown['fixed'] += $gain;
                }

                // 2. Verificar Centenas (hundred)
                if ($bet['type'] === 'hundred' && $bet['number'] === $winH) {
                    $gain = $bet['amount'] * $rates['hundred'];
                    $total += $gain;
                    $breakdown['hundred'] += $gain;
                }

                // 3. Verificar Tripletas (triplet)
                // Usan su propio multiplicador especial
                if ($bet['type'] === 'triplet' && $bet['number'] === $winF) {
                    $gain = $bet['amount'] * $rates['triplet'];
                    $total += $gain;
                    $breakdown['triplet'] += $gain;
                }

                // 4. Verificar Corridos (runners)
                // Los corridos pueden venir en apuestas 'fixed' o 'triplet'
                if (in_array($bet['type'], ['fixed', 'triplet'])) {
                    if ($bet['number'] === $winR1 && $bet['runner1'] > 0) {
                        $gain = $bet['runner1'] * $rates['runner1'];
                        $total += $gain;
                        $breakdown['runners'] += $gain;
                    }
                    if ($bet['number'] === $winR2 && $bet['runner2'] > 0) {
                        $gain = $bet['runner2'] * $rates['runner2'];
                        $total += $gain;
                        $breakdown['runners'] += $gain;
                    }
                }

                // 5. Verificar Parlets
                if ($bet['type'] === 'parlet') {
                    // El número del parlet es "05x10". Lo separamos.
                    $pair = explode('x', $bet['number']);
                    // Gana si AMBOS números están en el pool (Fijo, C1, C2)
                    if (in_array($pair[0], $winPool) && in_array($pair[1], $winPool)) {
                        $gain = $bet['amount'] * $rates['parlet'];
                        $total += $gain;
                        $breakdown['parlet'] += $gain;
                    }
                }
            }
        }

        return ['total' => $total, 'breakdown' => $breakdown];
    }

    private function getEffectiveRates(User $user): array
    {
        $config = $user->userConfig ?? $user->creator->adminConfig;

        if (!$config) throw new \Exception("Configuración de premios no encontrada.");

        return [
            'fixed'      => (int) $config->fixed,
            'hundred'    => (int) $config->hundred,
            'parlet'     => (int) $config->parlet,
            'triplet'    => (int) $config->triplet,
            'runner1'    => (int) $config->runner1,
            'runner2'    => (int) $config->runner2,
            'commission' => (float) $config->commission,
        ];
    }

    /**
     * Procesa y guarda el cierre, vinculándolo al banco.
     */
    public function processSettlement(int $userId, int $bankId, string $date, string $hourly)
    {
        return DB::transaction(function () use ($userId, $bankId, $date, $hourly) {
            $resultDto = $this->calculate($userId, $bankId, $date, $hourly);
            $dailyNumber = DailyNumber::whereDate('date', $date)->where('hourly', $hourly)->first();

            // 1. Guardar Recibo de Liquidación con bank_id
            $settlement = Settlement::create([
                'user_id' => $userId,
                'bank_id' => $bankId,
                'daily_number_id' => $dailyNumber->id,
                'date' => $date,
                'hourly' => $hourly,
                'total_sales' => $resultDto->total_sales,
                'commission_amt' => $resultDto->commission_amt,
                'prizes_amt' => $resultDto->total_prizes,
                'net_result' => $resultDto->final_balance,
                'applied_rates' => $resultDto->applied_rates,
                'prizes_breakdown' => $resultDto->prizes_breakdown,
                'created_by' => auth()->id()
            ]);

            // 2. Crear Transacción Automática vinculada al Banco
            $type = $resultDto->final_balance >= 0 ? 'income' : 'outcome';

            Transaction::create([
                'user_id' => $userId,
                'bank_id' => $bankId, // La deuda es con este banco
                'settlement_id' => $settlement->id,
                'amount' => abs($resultDto->final_balance),
                'type' => $type,
                'description' => "Liquidación automática - Banco: " . $settlement->bank->name,
                'date' => now(),
                'status' => 'approved',
                'created_by' => auth()->id(),
                'actioned_by' => auth()->id(),
                'actioned_at' => now(),
            ]);

            return $settlement;
        });
    }
}
