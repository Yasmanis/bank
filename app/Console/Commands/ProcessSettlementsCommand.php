<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DailyNumber;
use App\Models\BankList;
use App\Models\Settlement;
use App\Services\SettlementService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ProcessSettlementsCommand extends Command
{
    protected $signature = 'app:process-settlements {date? : YYYY-MM-DD} {hourly? : am o pm}';
    protected $description = 'Liquida ventas agrupadas por Usuario y Banco para un sorteo específico';

    protected SettlementService $service;

    public function __construct(SettlementService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $date = $this->argument('date') ?? now()->format('Y-m-d');
        $hourly = $this->argument('hourly') ?? (now()->hour < 15 ? 'am' : 'pm');

        $this->info("Iniciando liquidación masiva: $date ($hourly)");

        // 1. Verificar si existe el número ganador
        $win = DailyNumber::whereDate('date', $date)->where('hourly', $hourly)->first();
        if (!$win) {
            $this->error("Abortado: No existe número ganador para este turno.");
            return CommandAlias::FAILURE;
        }

        // 2. Identificar pares (Usuario + Banco) que tienen ventas validadas
        // Buscamos en bank_lists combinaciones únicas de user_id y bank_id
        $pendingRecords = BankList::query()
            ->whereDate('created_at', $date)
            ->where('hourly', $hourly)
            ->whereNotNull('bank_id')
            ->select('user_id', 'bank_id')
            ->distinct()
            ->with(['user', 'bank'])
            ->get();

        if ($pendingRecords->isEmpty()) {
            $this->info("No hay ventas pendientes de liquidación.");
            return CommandAlias::SUCCESS;
        }

        $processedCount = 0;
        $this->info("Se encontraron " . $pendingRecords->count() . " grupos para liquidar.");

        foreach ($pendingRecords as $record) {
            // 3. Verificar si ya existe una liquidación para este Usuario + Banco + Fecha + Hora
            $alreadySettled = Settlement::where('user_id', $record->user_id)
                ->where('bank_id', $record->bank_id)
                ->whereDate('date', $date)
                ->where('hourly', $hourly)
                ->exists();

            if ($alreadySettled) {
                continue;
            }

            try {
                // 4. Ejecutar la liquidación a través del servicio
                $this->service->processSettlement(
                    $record->user_id,
                    $record->bank_id,
                    $date,
                    $hourly
                );

                $this->line("<info>✔</info> Liquidado: {$record->user->name} en {$record->bank->name}");
                $processedCount++;

            } catch (\Throwable $e) {
                Log::error("Fallo liquidación (U:{$record->user_id} B:{$record->bank_id}): " . $e->getMessage());
                $this->error("✘ Error liquidando a {$record->user->name}: " . $e->getMessage());
            }
        }

        $this->info("\nProceso finalizado. Total liquidados: $processedCount");
        return CommandAlias::SUCCESS;
    }
}
