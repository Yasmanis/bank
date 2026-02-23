<?php

namespace App\Console\Commands;

use App\Services\Scrapers\LotteryConsensusService;
use Illuminate\Console\Command;
use App\Models\DailyNumber;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ScrapeLotteryResults extends Command
{
    protected $signature = 'app:scrape-results {hourly=am}';
    protected $description = 'Obtiene los números ganadores desde la web oficial';

    public function handle(LotteryConsensusService $consensus)
    {
        $this->info("Consultando fuentes para consenso...");

        $winner = $consensus->getConsensusResult($this->argument('hourly'));

        if (!$winner) {
            // Alerta: Las fuentes no coinciden, se requiere intervención humana
            Log::critical("FALLO DE CONSENSO: Las fuentes de lotería devuelven números distintos.");
            return CommandAlias::FAILURE;
        }


        // Si hay consenso, guardamos
        $daily = DailyNumber::updateOrCreate(
            ['date' => now()->format('Y-m-d'), 'hourly' => $this->argument('hourly')],
            [
                'hundred' => $winner['hundred'],
                'fixed'   => $winner['fixed'],
                'runner1' => $winner['r1'],
                'runner2' => $winner['r2'],
                'created_by' => 1
            ]
        );

        $this->info("Éxito: Consenso logrado para el número {$winner['hundred']}{$winner['fixed']}");


        return CommandAlias::SUCCESS;
    }
}
