<?php

namespace App\Services;

use App\Models\DailyNumber;
use App\Repositories\BankList\BankListRepository;
use App\Exceptions\UnprocessedLinesException;
use Illuminate\Support\Facades\DB;

class BankListService
{
    public function __construct(
        protected ListParserService $parser,
        protected BankListRepository $repository,
        protected SettlementService $settlementService
    ) {}

    /**
     * Orquestación completa: De texto de WhatsApp a Registro en Base de Datos
     */
    public function createFromChat($user, array $data)
    {
        return DB::transaction(function () use ($user, $data) {
            // 1. CONTROL DE IDEMPOTENCIA (Seguridad para el APK)
            if (!empty($data['client_uuid'])) {
                $existing = $this->repository->findDuplicate($user->id, $data['client_uuid']);
                if ($existing) {
                    return $existing;
                }
            }

            // 2. VALIDACIÓN DE CIERRE TÉCNICO (Antifraude)
            $now = now(); // America/Havana
            $hourly = $data['hourly'];
            $date = $data['date'] ?? $now->format('Y-m-d');

            $closingTimes = [
                'am' => '13:00', // 1:00 PM
                'pm' => '21:00', // 9:00 PM
            ];

            // Solo bloqueamos si la lista es para HOY y el usuario NO es admin
            // (Los admins pueden entrar listas a cualquier hora para rectificar)
            if ($date === $now->format('Y-m-d') && !$user->hasRole('super-admin|admin')) {
                if ($now->format('H:i') > $closingTimes[$hourly]) {
                    throw new \Exception("El sorteo {$hourly} cerró a las {$closingTimes[$hourly]}. No se aceptan más listas.");
                }
            }

            // 3. PROCESAMIENTO (Limpiar y Extraer)
            $cleanedText = $this->parser->cleanWhatsAppChat($data['text']);
            $extraction = $this->parser->extractBets($cleanedText);

            $bets = $extraction['bets'];
            $fullText = $extraction['full_text'];

            $this->validateExtraction($bets);

            // 4. CÁLCULO DE TOTALES DE VENTA
            $processedData = $this->parser->calculateTotals($bets, $fullText);

            $win = DailyNumber::whereDate('date', $date)
                ->where('hourly', $hourly)
                ->first();

            if ($win) {
                $rates = $user->getEffectiveRates();
                $prizesData = $this->settlementService->calculateFromBets($bets, $win, $rates);

                $processedData['prizes_preview'] = [
                    'found' => true,
                    'total_prizes' => $prizesData['total'],
                    'breakdown' => $prizesData['breakdown'],
                    'winning_number' => "{$win->hundred}-{$win->fixed}"
                ];
            }

            return $this->repository->store([
                'user_id'           => $user->id,
                'client_uuid'       => $data['client_uuid'] ?? null,
                'client_created_at' => $data['client_created_at'] ?? $now,
                'text'              => $data['text'],
                'processed_text'    => $processedData,
                'hourly'            => $hourly,
                'bank_id'           => $data['bank_id'] ?? null
            ]);
        });
    }

    protected function validateExtraction($bets)
    {
        $errorLines = $bets->where('type', 'error')->pluck('originalLine');
        if ($errorLines->isNotEmpty()) {
            throw new UnprocessedLinesException($errorLines->toArray());
        }

        if ($bets->where('type', '!=', 'error')->isEmpty()) {
            throw new \Exception("La lista no contiene ninguna jugada válida.");
        }
    }
}
