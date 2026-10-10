<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Support\SystemSettings;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$backupTime = SystemSettings::getString('backup_time', '14:00') ?? '14:00';
if (! preg_match('/^\d{2}:\d{2}$/', $backupTime)) {
    $backupTime = '14:00';
}
Schedule::command('app:backup-database')->dailyAt($backupTime);
Schedule::command('app:merge-latest-backups')->dailyAt('14:05');
Schedule::command('security:review')->dailyAt('14:10');
