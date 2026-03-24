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
     * Calcula la liquidación filtrando por BANCO considerando validaciones manuales.
     */
    public function calculate(int $userId, int $bankId, string $date, string $hourly): SettlementResultDto
    {
        $user = User::findOrFail($userId);
        $rates = $user->getEffectiveRates();

        $win = DailyNumber::whereDate('date', $date)->where('hourly', $hourly)->first();
        if (!$win) throw new \Exception("Falta el número ganador para este turno.");

        $lists = BankList::where('user_id', $userId)
            ->where('bank_id', $bankId)
            ->whereDate('created_at', $date)
            ->where('hourly', $hourly)
            ->get();

        if ($lists->isEmpty()) throw new \Exception("No hay ventas registradas para este usuario en este banco.");

        // --- 1. LÓGICA DE VENTAS TOTALES (Prioridad Manual) ---
        $totalSales = $lists->sum(function($list) {
            // Si el admin puso los totales a mano, usamos esos. Si no, lo del bot.
            return $list->manual_results
                ? (float)($list->manual_results['total'] ?? 0)
                : (float)($list->processed_text['total'] ?? 0);
        });

        // --- 2. LÓGICA DE PREMIOS (Prioridad Manual) ---
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

    /**
     * Calcula los premios recorriendo las listas y detectando si son manuales o automáticas.
     */
    private function calculateTotalPrizes(Collection $lists, DailyNumber $win, array $rates): array
    {
        $total = 0;
        $breakdown = [
            'fixed' => 0, 'hundred' => 0, 'parlet' => 0,
            'triplet' => 0, 'runners' => 0, 'manual' => 0
        ];

        foreach ($lists as $list) {
            // CASO A: El administrador definió el premio manualmente (Imagen o Error de texto)
            if ($list->manual_results && isset($list->manual_results['prizes'])) {
                $manualPrize = (float)$list->manual_results['prizes'];
                $total += $manualPrize;
                $breakdown['manual'] += $manualPrize;
            }
            // CASO B: Procesamiento automático del bot
            else {
                $bets = collect($list->processed_text['bets'] ?? []);
                $autoResult = $this->calculatePrizesFromBets($bets, $win, $rates);

                $total += $autoResult['total'];
                // Sumamos al breakdown general
                foreach ($autoResult['breakdown'] as $key => $amount) {
                    $breakdown[$key] += $amount;
                }
            }
        }

        return ['total' => $total, 'breakdown' => $breakdown];
    }

    /**
     * CÁLCULO CORE: Recibe una colección de DetectedBet y devuelve los premios (Igual que antes)
     */
    public function calculatePrizesFromBets(Collection $bets, DailyNumber $win, array $rates): array
    {
        //TODO:
        //-Calculo de Tripletas
        // WINNER
        //-              "fixed": "75",
        //            "hundred": "6",
        //            "runner1": "95",
        //            "runner2": "21",

        //Rates
        //                  "fixed" => 75,
        //                "hundred" => 300,
        //                "parlet" => 400,
        //                "runner1" => 25,
        //                "runner2" => 25,
        //                "triplet" => 70,
        //                "commission" => 25.00,


        //- 75-20-20-20 = 75 * 20 = 1500
        //- 95-20-20-20 = 70 * 20 = 1400  Se paga a 70 porque esta linea es una tripleta
        //- 21-20-20-20 = 70 * 20 = 1400  Se paga a 70 porque esta linea es una tripleta

        //Calculo de corrido
        //- 75-20-10 = (20 * 75) + (10 * 25) = 1750  Si el que salio fue el fijo se paga los primero 20 a 75(fixed) los otros 10 pesos a 25(runner1)
        //- 95-20-10 = (10 * 25) = 250 solo se paga el corrido pero a precio de runner1
        //- 21-20-10 = (10 * 25) = 250 solo se paga el corrido pero a precio de runner1



        $total = 0;
        $breakdown = ['fixed' => 0, 'hundred' => 0, 'parlet' => 0, 'triplet' => 0, 'runners' => 0];

        $winF = $win->fixed;
        $winH = $win->hundred . $win->fixed;
        $winR1 = $win->runner1;
        $winR2 = $win->runner2;
        $winPool = [$winF, $winR1, $winR2];

        foreach ($bets as $bet) {
            $bet = is_object($bet) ? (array) $bet : $bet;

            if ($bet['type'] === 'fixed' && $bet['number'] === $winF) {
                $gain = $bet['amount'] * $rates['fixed'];
                $total += $gain; $breakdown['fixed'] += $gain;
            }
            if ($bet['type'] === 'hundred' && $bet['number'] === $winH) {
                $gain = $bet['amount'] * $rates['hundred'];
                $total += $gain; $breakdown['hundred'] += $gain;
            }
            if ($bet['type'] === 'triplet' && $bet['number'] === $winF) {
                $gain = $bet['amount'] * $rates['triplet'];
                $total += $gain; $breakdown['triplet'] += $gain;
            }
            if (in_array($bet['type'], ['fixed', 'triplet'])) {
                if ($bet['number'] === $winR1 && ($bet['runner1'] ?? 0) > 0) {
                    $gain = $bet['runner1'] * $rates['runner1'];
                    $total += $gain; $breakdown['runners'] += $gain;
                }
                if ($bet['number'] === $winR2 && ($bet['runner2'] ?? 0) > 0) {
                    $gain = $bet['runner2'] * $rates['runner2'];
                    $total += $gain; $breakdown['runners'] += $gain;
                }
            }
            if ($bet['type'] === 'parlet') {
                $pair = explode('x', $bet['number']);
                if (in_array($pair[0], $winPool) && in_array($pair[1], $winPool)) {
                    $gain = $bet['amount'] * $rates['parlet'];
                    $total += $gain; $breakdown['parlet'] += $gain;
                }
            }
        }

        return ['total' => $total, 'breakdown' => $breakdown];
    }

    /**
     * El método de preview sigue funcionando para el texto que se está escribiendo
     */
    public function calculateFromBets(Collection $bets, DailyNumber $win, array $rates): array
    {
        return $this->calculatePrizesFromBets($bets, $win, $rates);
    }

    /**
     * Procesa y guarda el cierre.
     */
    public function processSettlement(int $userId, int $bankId, string $date, string $hourly)
    {
        return DB::transaction(function () use ($userId, $bankId, $date, $hourly) {
            $resultDto = $this->calculate($userId, $bankId, $date, $hourly);
            $dailyNumber = DailyNumber::whereDate('date', $date)->where('hourly', $hourly)->first();

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

            $type = $resultDto->final_balance >= 0 ? 'income' : 'outcome';

            Transaction::create([
                'user_id' => $userId,
                'bank_id' => $bankId,
                'settlement_id' => $settlement->id,
                'amount' => abs($resultDto->final_balance),
                'type' => $type,
                'description' => "Liquidación automática - Turno: " . strtoupper($hourly),
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
