<?php

namespace App\Console\Commands;

use App\Services\Scrapers\LotteryConsensusService;
use Illuminate\Console\Command;
use App\Models\DailyNumber;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ScrapeLotteryResults extends Command
{
    protected $signature = 'app:scrape-results {hourly=am} {date? : La fecha opcional YYYY-MM-DD}';
    protected $description = 'Obtiene los números ganadores desde la web oficial mediante consenso';

    public function handle(LotteryConsensusService $consensus)
    {
        $hourly = $this->argument('hourly');
        $date = $this->argument('date') ?? (
        ($hourly === 'pm' && now()->hour < 10)
            ? now()->subDay()->format('Y-m-d')
            : now()->format('Y-m-d')
        );

        $exists = DailyNumber::whereDate('date', $date)
            ->where('hourly', $hourly)
            ->exists();

        if ($exists) {
            $this->comment("El número ganador para {$date} ({$hourly}) ya se encuentra registrado. Saltando scraping...");
            return CommandAlias::SUCCESS;
        }

        $this->info("Consultando fuentes para consenso para: {$date} ({$hourly})...");

        // 2. Ejecutar el scraping solo si no existe el dato
        $winner = $consensus->getConsensusResult($hourly);

        if (!$winner) {
            // Alerta: Las fuentes no coinciden o no hay resultados aún
            $this->error("Fallo de consenso: Las fuentes no coinciden o no han publicado el resultado.");
            Log::warning("FALLO DE CONSENSO: No se pudo determinar el ganador automáticamente para {$date} ({$hourly}).");
            return CommandAlias::FAILURE;
        }

        // 3. Guardar el resultado (usamos updateOrCreate por seguridad, aunque ya validamos que no existe)
        DailyNumber::updateOrCreate(
            ['date' => $date, 'hourly' => $hourly],
            [
                'hundred' => $winner['hundred'],
                'fixed' => $winner['fixed'],
                'runner1' => $winner['r1'],
                'runner2' => $winner['r2'],
                'created_by' => 1 // ID del sistema/admin
            ]
        );

        $this->info("Éxito: Consenso logrado para el número {$winner['hundred']}-{$winner['fixed']}");

        return CommandAlias::SUCCESS;

    }
}
