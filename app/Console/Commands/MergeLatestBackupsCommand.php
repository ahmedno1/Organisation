<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

class MergeLatestBackupsCommand extends Command
{
    protected $signature = 'app:merge-latest-backups';
    protected $description = 'Compare the latest two SQLite backups and merge new rows from newest into older copy';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            $this->warn('Backup directory does not exist.');
            return self::SUCCESS;
        }

        $files = collect(File::files($backupDir))
            ->filter(fn ($file) => str_ends_with(strtolower($file->getFilename()), '.sqlite'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        if ($files->count() < 2) {
            $this->warn('Need at least two backup files to compare and merge.');
            return self::SUCCESS;
        }

        $newer = $files[0]->getPathname();
        $older = $files[1]->getPathname();

        if (sha1_file($newer) === sha1_file($older)) {
            $this->info('Latest two backup files are identical. Merge skipped.');
            return self::SUCCESS;
        }

        $mergedPath = $backupDir.'/db_merged_'.now()->format('Ymd_His').'.sqlite';
        File::copy($older, $mergedPath);

        try {
            $pdo = new PDO('sqlite:'.$mergedPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("ATTACH DATABASE '".str_replace("'", "''", $newer)."' AS src");

            $tablesStmt = $pdo->query("SELECT name FROM main.sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $mergedTables = 0;

            $pdo->beginTransaction();
            foreach ($tables as $tableName) {
                $safeTable = '"'.str_replace('"', '""', (string) $tableName).'"';
                $tableInfo = $pdo->query("PRAGMA main.table_info($safeTable)")->fetchAll(PDO::FETCH_ASSOC);
                if ($tableInfo === []) {
                    continue;
                }

                $columns = array_map(fn ($col) => '"'.str_replace('"', '""', (string) $col['name']).'"', $tableInfo);
                $pkColumns = array_values(array_filter($tableInfo, fn ($col) => (int) ($col['pk'] ?? 0) > 0));
                $colsSql = implode(', ', $columns);

                if (count($pkColumns) === 1) {
                    $pk = '"'.str_replace('"', '""', (string) $pkColumns[0]['name']).'"';
                    $sql = "INSERT INTO main.$safeTable ($colsSql)
                            SELECT $colsSql FROM src.$safeTable s
                            WHERE NOT EXISTS (SELECT 1 FROM main.$safeTable d WHERE d.$pk = s.$pk)";
                } else {
                    $sql = "INSERT OR IGNORE INTO main.$safeTable ($colsSql) SELECT $colsSql FROM src.$safeTable";
                }

                $pdo->exec($sql);
                $mergedTables++;
            }
            $pdo->commit();
            $pdo->exec('DETACH DATABASE src');

            $logLine = '['.now()->format('Y-m-d H:i:s')."] merged older=".basename($older).' newer='.basename($newer).' result='.basename($mergedPath).' tables='.$mergedTables.PHP_EOL;
            File::append($backupDir.'/merge_history.log', $logLine);
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            File::delete($mergedPath);
            $this->error('Merge failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Merged backup created: '.$mergedPath);
        return self::SUCCESS;
    }
}
