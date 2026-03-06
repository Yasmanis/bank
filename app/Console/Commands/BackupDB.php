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
        $now = Carbon::now()->toDateString() . '-' . Carbon::now()->timestamp;
        $db = env('DB_DATABASE', 'forge');
        $user = env('DB_USERNAME', 'forge');
        $pass = env('DB_PASSWORD', '');
        $backupPath = storage_path('backup');
        $sqlFile = $backupPath . '/dump-' . $now . '.sql';
        $zipFile = $backupPath . '/dump-' . $now . '.zip';

        try {
            // Create SQL dump
            $dump = new IMysqldump\Mysqldump('mysql:host=localhost;dbname=' . $db, $user, $pass);
            $dump->start($sqlFile);

            // Compress the SQL dump into a ZIP file
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
                $zip->addFile($sqlFile, basename($sqlFile));
                $zip->setCompressionName(basename($sqlFile), ZipArchive::CM_DEFLATE);
                $zip->close();

                // Delete the original SQL file after zipping
                unlink($sqlFile);
            } else {
                Log::error('Error al crear el archivo ZIP');
            }
        } catch (\Exception $e) {
            Log::error('mysqldump-php error: ' . $e->getMessage());
            return;
        }

        Log::info('Backup de base de datos guardado y comprimido.');

        activity()->log('Salva de BD');
    }
}
