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
            // 1. Limpiar y Extraer (Usa el ListParserService)
            $cleanedText = $this->parser->cleanWhatsAppChat($data['text']);
            $extraction = $this->parser->extractBets($cleanedText);

            $bets = $extraction['bets'];
            $fullText = $extraction['full_text'];
            $this->validateExtraction($bets);
            // 3. Calcular Totales de Venta
            $processedData = $this->parser->calculateTotals($bets, $fullText);
            $date = $data['date'] ?? now()->format('Y-m-d');
            $win = DailyNumber::whereDate('date', $date)
                ->where('hourly', $data['hourly'])
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

            // 5. Guardar mediante el Repositorio
            return $this->repository->store([
                'user_id' => $user->id,
                'text' => $data['text'],
                'processed_text' => $processedData,
                'hourly' => $data['hourly'],
                'full_text_cleaned' => $fullText,
                // El bank_id puede ir aquí si ya se conoce o ser null para validar después
                'bank_id' => $data['bank_id'] ?? null
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
