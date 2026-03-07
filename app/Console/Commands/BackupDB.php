<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Ifsnop\Mysqldump as IMysqldump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class BackupDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup_db:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup diaria de la base de datos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        set_time_limit(0);
        ini_set('memory_limit', '8912M');

        $now = Carbon::now()->format('Y-m-d-H-i-s');

        // USAR SIEMPRE config() EN LUGAR DE env()
        $db   = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');

        $backupPath = storage_path('app/backup'); // Carpeta recomendada en storage/app

        // Asegurarse de que la carpeta existe
        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $sqlFile = $backupPath . '/dump-' . $now . '.sql';
        $zipFile = $backupPath . '/dump-' . $now . '.zip';

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$db}";

            $dump = new IMysqldump\Mysqldump($dsn, $user, $pass);
            $dump->start($sqlFile);

            // Comprimir
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
                $zip->addFile($sqlFile, basename($sqlFile));
                $zip->close();

                if (file_exists($sqlFile)) {
                    unlink($sqlFile);
                }
            } else {
                Log::error('BackupDB: Error al crear el archivo ZIP');
                return;
            }

            Log::info("BackupDB: Base de datos '$db' guardada con éxito en $zipFile");
            activity()->log('Salva de BD generada correctamente');

        } catch (\Exception $e) {
            Log::error('BackupDB Error: ' . $e->getMessage());
            $this->error('Fallo en el backup: ' . $e->getMessage());
        }
    }
}
