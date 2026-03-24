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
            // --- IDEMPOTENCIA ---
            if (!empty($data['client_uuid'])) {
                $existing = $this->repository->findDuplicate($user->id, $data['client_uuid']);
                if ($existing) return $existing;
            }

            // --- PROCESAMIENTO DE ARCHIVO ---
            $filePath = null;
            if (request()->hasFile('file')) {
                // Se guarda en storage/app/public/lists
                $filePath = request()->file('file')->store('lists', 'public');
            }

            // --- TIEMPOS Y FECHAS ---
            $now = now();
            $clientCreatedAt = !empty($data['client_created_at'])
                ? \Illuminate\Support\Carbon::parse($data['client_created_at'])->timezone(config('app.timezone'))
                : $now;

            $status = BankList::STATUS_PENDING;
            $errorLog = null;
            $processedData = [];
            $fullText = '';
            $bets = collect();

            $hourly = $data['hourly'] ?? null;
            if (!$hourly) {
                $hourly = ($clientCreatedAt->format('H:i') <= '13:00') ? 'am' : 'pm';
                $data['hourly'] = $hourly;
            }

            try {
                // 2. VALIDACIÓN DE CIERRE TÉCNICO
                $this->checkClosingTime($hourly, $data['date'] ?? $now->format('Y-m-d'), $user, $clientCreatedAt);

                if (!empty($data['text'])) {
                    $extraction = $this->parser->extractBets($data['text']);
                    $bets = $extraction['bets'];
                    $fullText = $extraction['full_text'];
                    $this->validateExtraction($bets);
                    $processedData = $this->parser->calculateTotals($bets, $fullText);
                    $processedData = $this->enrichWithPrizes($user, $processedData, $bets, $data);
                } else {
                    $processedData = $this->parser->calculateTotals(collect(), '');
                }

            } catch (UnprocessedLinesException $e) {
                $status = BankList::STATUS_ERROR;
                $errorLog = ['unprocessed_lines' => $e->getLines()];
                $processedData = $this->parser->calculateTotals($bets, $fullText);
            } catch (\Throwable $th) {
                $status = BankList::STATUS_ERROR;
                $errorLog = ['system_error' => $th->getMessage()];
            }

            // 4. GUARDADO EN REPOSITORIO
            return $this->repository->store([
                'user_id' => $user->id,
                'client_uuid' => $data['client_uuid'] ?? null,
                'client_created_at' => $clientCreatedAt,
                'text' => $data['text'] ?? null,
                'file_path' => $filePath,
                'processed_text' => $processedData,
                'error_log' => $errorLog,
                'status' => $status,
                'hourly' => $hourly,
                'bank_id' => $data['bank_id'] ?? (\App\Models\Bank::first()->id ?? null),
            ]);
        });

        // 2. MANEJO DE EXCEPCIONES POST-TRANSACCIÓN
        if ($record->status === BankList::STATUS_ERROR) {
            if (!empty($record->error_log['system_error'])) {
                throw new \Exception($record->error_log['system_error']);
            }

            if (!empty($record->error_log['unprocessed_lines'])) {
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
        if ($date === now()->format('Y-m-d') && !$user->hasRole(['super-admin', 'admin'])) {
            // Validamos contra el reloj que mandó el teléfono (clientTime)
            if ($clientTime->format('H:i') > $closingTimes[$hourly]) {
                throw new \Exception("La lista fue creada fuera de horario ({$clientTime->format('H:i')}).");
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



    public function getUnifiedTurnReport(int $userId, string $date, string $hourly)
    {
        $lists = $this->repository->getListsForUnification($userId, $date, $hourly);

        if ($lists->isEmpty()) return null;

        // 1. Unimos todo el texto original de los diferentes mensajes
        $combinedRawText = $lists->pluck('text')->implode("\n");

        // 2. Pasamos el bloque completo por el motor de extracción
        // Esto recalcula totales y agrupa todos los errores en un solo lugar
        $extraction = $this->parser->extractBets($combinedRawText);
        $bets = $extraction['bets'];
        $fullCleanText = $extraction['full_text'];

        // 3. Calculamos totales de venta del bloque unificado
        $totals = $this->parser->calculateTotals($bets, $fullCleanText);

        // 4. Intentamos calcular premios si el número ya salió
        $win = DailyNumber::whereDate('date', $date)->where('hourly', $hourly)->first();
        if ($win) {
            $user = User::find($userId);
            $rates = $user->getEffectiveRates();
            $prizesData = $this->settlementService->calculateFromBets($bets, $win, $rates);

            $totals['prizes_preview'] = [
                'found' => true,
                'total_prizes' => $prizesData['total'],
                'breakdown' => $prizesData['breakdown'],
                'winning_number' => "{$win->hundred}-{$win->fixed}"
            ];
        }

        // 5. Creamos un objeto "virtual" compatible con el DTO
        // Usamos 'new BankList' sin guardar para que el DTO pueda leerlo como un modelo
        return new BankList([
            'id' => 0, // ID 0 indica que es un consolidado
            'user_id' => $userId,
            'hourly' => $hourly,
            'text' => $combinedRawText,
            'processed_text' => $totals,
            'status' => 'unified',
            'created_at' => \Carbon\Carbon::parse($date),
            'error_log' => [
                'unprocessed_lines' => $bets->where('type', 'error')->pluck('originalLine')->toArray()
            ]
        ]);
    }
}
