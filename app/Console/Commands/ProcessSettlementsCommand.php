<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DailyNumber;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Support\Facades\Log;

class ProcessSettlementsCommand extends Command
{
    // El comando se ejecutará como: php artisan app:process-settlements {date} {hourly}
    protected $signature = 'app:process-settlements {date? : La fecha YYYY-MM-DD} {hourly? : am o pm}';
    protected $description = 'Liquida automáticamente las ventas de todos los usuarios para un sorteo específico';

    protected $service;

    public function __construct(SettlementService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        // 1. Obtener parámetros o usar el tiempo actual
        $date = $this->argument('date') ?? now()->format('Y-m-d');
        $hourly = $this->argument('hourly') ?? (now()->hour < 15 ? 'am' : 'pm');

        $this->info("Iniciando liquidación para: $date ($hourly)");

        // 2. Verificar si ya existe el número ganador
        $win = DailyNumber::where('date', $date)->where('hourly', $hourly)->first();
        if (!$win) {
            $this->error("No se puede liquidar: Falta registrar el número ganador.");
            return Command::FAILURE;
        }

        // 3. Buscar usuarios con rol 'user' que tengan listas en ese turno
        // y que aún NO hayan sido liquidados
        $users = User::role('user')
            ->whereHas('bankLists', function($q) use ($date, $hourly) {
                $q->whereDate('created_at', $date)->where('hourly', $hourly);
            })
            ->whereDoesntHave('settlements', function($q) use ($date, $hourly) {
                $q->where('date', $date)->where('hourly', $hourly);
            })
            ->get();

        if ($users->isEmpty()) {
            $this->info("No hay usuarios pendientes de liquidación para este turno.");
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            try {
                $this->service->processSettlement($user->id, $date, $hourly);
                $bar->advance();
            } catch (\Throwable $e) {
                Log::error("Error liquidando usuario {$user->id}: " . $e->getMessage());
                $this->error("\nError en usuario {$user->name}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->info("\nLiquidación completada exitosamente.");
        return Command::SUCCESS;
    }
}
