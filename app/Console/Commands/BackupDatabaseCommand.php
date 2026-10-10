<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'app:backup-database';
    protected $description = 'Create a local backup copy of database file';

    public function handle(): int
    {
        $connection = config('database.default');
        $databasePath = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite') {
            $this->warn('Auto backup command currently supports sqlite file backups only.');
            return self::SUCCESS;
        }

        if (! File::exists($databasePath)) {
            $this->error("Database file not found: {$databasePath}");
            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);
        $backupFile = $backupDir.'/db_backup_'.now()->format('Ymd_His').'.sqlite';

        File::copy($databasePath, $backupFile);
        $this->info("Backup created: {$backupFile}");

        return self::SUCCESS;
    }
}
