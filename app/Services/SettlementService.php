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
    public function calculate(int $userId, string $date, string $hourly): SettlementResultDto
    {
        $user = User::findOrFail($userId);
        $rates = $user->getEffectiveRates();

        $win = DailyNumber::whereDate('date', $date)
            ->where('hourly', $hourly)
            ->first();

        if (!$win) {
            throw new \Exception("No hay número ganador registrado para esta fecha y horario.");
        }

        $lists = BankList::where('user_id', $userId)
            ->whereDate('created_at', $date)
            ->where('hourly', $hourly)
            ->get();

        $totalSales = $lists->sum(fn($l) => $l->processed_text['total'] ?? 0);

        // --- LÓGICA DE CÁLCULO DE PREMIOS ---
        $prizesData = $this->calculateTotalPrizes($lists, $win, $rates);

        $commissionAmt = $totalSales * ($rates['commission'] / 100);
        $netSales = $totalSales - $commissionAmt;

        // Saldo final: Lo que el listero debe entregar al admin (o cobrar si es negativo)
        $finalBalance = $netSales - $prizesData['total'];

        return new SettlementResultDto(
            user_id: $user->id,
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

    public function processSettlement(int $userId, string $date, string $hourly)
    {
        return DB::transaction(function () use ($userId, $date, $hourly) {
            // 1. Calculamos los datos (usando el método calculate que ya tenemos)
            $resultDto = $this->calculate($userId, $date, $hourly);

            $dailyNumber = DailyNumber::where('date', $date)->where('hourly', $hourly)->first();

            // 2. Crear el registro de liquidación
            $settlement = Settlement::create([
                'user_id' => $userId,
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

            // 3. Crear la Transacción Automática
            // Si el balance es positivo, el listero debe dinero al admin (income para el admin)
            // Si es negativo, el admin debe al listero (outcome para el admin)
            $type = $resultDto->final_balance >= 0 ? 'income' : 'outcome';

            Transaction::create([
                'user_id' => $userId,
                'settlement_id' => $settlement->id,
                'amount' => abs($resultDto->final_balance), // Siempre positivo en transacciones
                'type' => $type,
                'description' => "Liquidación automática: " . $date . " (" . strtoupper($hourly) . ")",
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
