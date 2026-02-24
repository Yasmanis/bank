<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BankList;
use Illuminate\Support\Facades\Log;

class CleanupOldLists extends Command
{
    /**
     * El nombre y firma del comando.
     */
    protected $signature = 'app:cleanup-lists';

    /**
     * La descripción del comando.
     */
    protected $description = 'Elimina permanentemente las listas creadas hace más de 48 horas';

    public function handle()
    {
        $this->info('Iniciando limpieza de listas antiguas...');

        $threshold = now()->subHours(48);

        $count = BankList::where('created_at', '<', $threshold)->count();

        if ($count === 0) {
            $this->info('No hay listas antiguas para eliminar.');
            return Command::SUCCESS;
        }
        BankList::where('created_at', '<', $threshold)->forceDelete();
        Log::info("Limpieza automática: Se han eliminado permanentemente $count listas anteriores a $threshold.");
        $this->info("Éxito: Se eliminaron $count listas.");

        return Command::SUCCESS;
    }
}
