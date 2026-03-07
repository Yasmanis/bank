<?php

namespace App\Services;

use App\Models\BankList;
use App\Models\DailyNumber;
use App\Models\User;
use App\Repositories\BankList\BankListRepository;
use App\Exceptions\UnprocessedLinesException;
use Illuminate\Support\Collection;
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
    public function createFromChat(User $user, array $data)
    {
        return DB::transaction(function () use ($user, $data) {
            if (!empty($data['client_uuid'])) {
                $existing = $this->repository->findDuplicate($user->id, $data['client_uuid']);
                if ($existing) return $existing;
            }
            $now = now();
            $clientCreatedAt = !empty($data['client_created_at'])
                ? \Illuminate\Support\Carbon::parse($data['client_created_at'])->timezone(config('app.timezone'))
                : $now;

            $status = BankList::STATUS_PENDING;
            $errorLog = null;
            $processedData = [];

            try {
                // 2. VALIDACIÓN DE CIERRE TÉCNICO (Evita fraudes)
                $this->checkClosingTime($data['hourly'], $data['date'] ?? $now->format('Y-m-d'), $user);
                $extraction = $this->parser->extractBets($data['text']);
                $bets = $extraction['bets'];
                $fullText = $extraction['full_text'];

                $this->validateExtraction($bets);
                $processedData = $this->parser->calculateTotals($bets, $fullText);
                $processedData = $this->enrichWithPrizes($user, $processedData, $bets, $data);

            } catch (UnprocessedLinesException $e) {
                $status = BankList::STATUS_ERROR;
                $errorLog = ['unprocessed_lines' => $e->getLines()];
                $processedData = $this->parser->calculateTotals($bets ?? collect(), $fullText ?? '');
            } catch (\Throwable $th) {
                $status = BankList::STATUS_ERROR;
                $errorLog = ['system_error' => $th->getMessage()];
            }

            $record = $this->repository->store([
                'user_id'           => $user->id,
                'client_uuid'       => $data['client_uuid'] ?? null,
                'client_created_at' => $clientCreatedAt,
                'text'              => $data['text'],
                'processed_text'    => $processedData,
                'error_log'         => $errorLog,
                'status'            => $status,
                'hourly'            => $data['hourly'],
                'bank_id'           => $data['bank_id'] ?? null,
            ]);

            // Si el estado es error, lanzamos la excepción DESPUÉS de guardar
            if ($status === BankList::STATUS_ERROR) {
                throw new UnprocessedLinesException($errorLog['unprocessed_lines'] ?? [$errorLog['system_error']]);
            }

            return $record;
        });
    }

    /**
     * Valida que no haya líneas con error y que existan apuestas válidas.
     */
    protected function validateExtraction(Collection $bets): void
    {
        $errorLines = $bets->where('type', 'error')->pluck('originalLine');
        if ($errorLines->isNotEmpty()) {
            throw new UnprocessedLinesException($errorLines->toArray());
        }

        if ($bets->where('type', '!=', 'error')->isEmpty()) {
            throw new \Exception("La lista no contiene ninguna jugada válida.");
        }
    }

    /**
     * Valida si el sorteo de hoy ya cerró según la hora del servidor.
     */
    protected function checkClosingTime(string $hourly, string $date, User $user): void
    {
        $now = now();
        $closingTimes = [
            'am' => '13:00', // 1:00 PM
            'pm' => '21:00', // 9:00 PM
        ];

        // Los administradores pueden saltar el cierre para rectificar datos
        if ($date === $now->format('Y-m-d') && !$user->hasRole(['super-admin', 'admin'])) {
            if ($now->format('H:i') > $closingTimes[$hourly]) {
                throw new \Exception("El sorteo {$hourly} ya cerró a las {$closingTimes[$hourly]}.");
            }
        }
    }

    /**
     * Si el número ganador ya existe, añade el resultado de premios al JSON procesado.
     */
    protected function enrichWithPrizes(User $user, array $processedData, Collection $bets, array $data): array
    {
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

        return $processedData;
    }
}
