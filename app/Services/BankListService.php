<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BankList;
use App\Models\DailyNumber;
use App\Models\User;
use App\Repositories\BankList\BankListRepository;
use App\Exceptions\UnprocessedLinesException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankListService
{
    public function __construct(
        protected ListParserService  $parser,
        protected BankListRepository $repository,
        protected SettlementService  $settlementService
    )
    {
    }

    /**
     * Orquestación completa: De texto de WhatsApp a Registro en Base de Datos
     */
    public function createFromChat($user, array $data)
    {
        // 1. Ejecutamos la lógica y el guardado dentro de la transacción
        $record = DB::transaction(function () use ($user, $data) {
            if (!empty($data['client_uuid'])) {
                $existing = $this->repository->findDuplicate($user->id, $data['client_uuid']);
                if ($existing) return $existing;
            }

            $now = now();
            $clientCreatedAt = !empty($data['client_created_at'])
                ? Carbon::parse($data['client_created_at'])->timezone(config('app.timezone'))
                : $now;

            $status = BankList::STATUS_PENDING;
            $errorLog = null;
            $processedData = [];
            $fullText = '';
            $bets = collect();

            try {
                $this->checkClosingTime($data['hourly'], $data['date'] ?? $now->format('Y-m-d'), $user,$clientCreatedAt);

                $extraction = $this->parser->extractBets($data['text']);
                $bets = $extraction['bets'];
                $fullText = $extraction['full_text'];

                $this->validateExtraction($bets);

                $processedData = $this->parser->calculateTotals($bets, $fullText);
                $processedData = $this->enrichWithPrizes($user, $processedData, $bets, $data);

            } catch (UnprocessedLinesException $e) {
                $status = BankList::STATUS_ERROR;
                $errorLog = ['unprocessed_lines' => $e->getLines()];
                $processedData = $this->parser->calculateTotals($bets, $fullText);
            } catch (\Throwable $th) {
                $status = BankList::STATUS_ERROR;
                $errorLog = ['system_error' => $th->getMessage()];
            }

            // GUARDADO: Esto se confirmará porque la transacción termina aquí
            return $this->repository->store([
                'user_id' => $user->id,
                'client_uuid' => $data['client_uuid'] ?? null,
                'client_created_at' => $clientCreatedAt,
                'text' => $data['text'],
                'processed_text' => $processedData,
                'error_log' => $errorLog,
                'status' => $status,
                'hourly' => $data['hourly'],
                'bank_id' => $data['bank_id'] ?? Bank::first()->id,
            ]);
        });

        // 2. FUERA DE LA TRANSACCIÓN:
        // Ahora que el registro ya está seguro en la base de datos,
        if ($record->status === BankList::STATUS_ERROR) {
            // CASO A: Error de Sistema o Lógica (Cierre, lista vacía, etc.)
            if (!empty($record->error_log['system_error'])) {
                // Lanzamos una excepción normal con el mensaje (string)
                throw new \Exception($record->error_log['system_error']);
            }

            // CASO B: Error de Extracción (Líneas que no se entendieron)
            if (!empty($record->error_log['unprocessed_lines'])) {
                // Lanzamos tu excepción personalizada con el array de líneas
                throw new UnprocessedLinesException($record->error_log['unprocessed_lines']);
            }
        }

        return $record;
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
    protected function checkClosingTime(string $hourly, string $date, User $user, Carbon $clientTime): void
    {
        // Horarios de cierre
        $closingTimes = [
            'am' => '13:00', // 1:00 PM
            'pm' => '21:00', // 9:00 PM
        ];

        // Los admins pueden saltar la validación de la hora del reloj
        if ($date === now()->format('Y-m-d') && !$user->hasRole(['super-admin', 'admin'])) {
            // Validamos contra el reloj que mandó el teléfono (clientTime)
            if ($clientTime->format('H:i') > $closingTimes[$hourly]) {
                throw new \Exception("La lista fue creada fuera de horario ({$clientTime->format('H:i')}).");
            }

            // Seguridad extra: Si la lista llega al servidor con mucha diferencia de tiempo (ej. 4 horas después)
            // podrías poner un límite de sincronización.
//            if (now()->diffInHours($clientTime) > 12) {
//                throw new \Exception("La lista ha tardado demasiado en sincronizarse y ya no es válida.");
//            }
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
