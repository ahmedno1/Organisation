<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class HealthCheckController extends Controller
{
    public function index(): View
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'api_key' => $this->checkApiKey(),
            'backups' => $this->checkBackups(),
            'notifications' => $this->checkNotifications(),
            'users' => $this->checkUsers(),
        ];

        return view('developer.health.index', compact('checks'));
    }

    public function runBackup(Request $request): RedirectResponse
    {
        Artisan::call('app:backup-database');
        $output = trim(Artisan::output());

        return back()->with('status', $output !== '' ? $output : 'Backup command executed.');
    }

    /**
     * @return array{status:string,details:string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');
            return ['status' => 'ok', 'details' => 'Database connection is healthy.'];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'details' => $e->getMessage()];
        }
    }

    /**
     * @return array{status:string,details:string}
     */
    private function checkCache(): array
    {
        try {
            $key = 'health_check_'.uniqid();
            Cache::put($key, 'ok', 60);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value !== 'ok') {
                return ['status' => 'fail', 'details' => 'Cache write/read mismatch.'];
            }

            return ['status' => 'ok', 'details' => 'Cache read/write test passed.'];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'details' => $e->getMessage()];
        }
    }

    /**
     * @return array{status:string,details:string}
     */
    private function checkQueue(): array
    {
        $driver = (string) config('queue.default');
        return [
            'status' => 'ok',
            'details' => $driver === 'sync'
                ? 'Queue is enabled in sync mode.'
                : "Queue is enabled with {$driver} driver.",
        ];
    }

    /**
     * @return array{status:string,details:string}
     */
    private function checkApiKey(): array
    {
        $key = SystemSettings::getString('api_access_key', (string) config('api.key'));

        if (! $key) {
            return ['status' => 'ok', 'details' => 'API endpoint is active. No custom key configured.'];
        }

        return ['status' => 'ok', 'details' => 'API endpoint is active and key is configured.'];
    }

    /**
     * @return array{status:string,details:string}
     */
    private function checkBackups(): array
    {
        $path = storage_path('app/backups');

        if (! File::exists($path)) {
            return ['status' => 'ok', 'details' => 'Backup directory will be created on first backup run.'];
        }

        $files = collect(File::files($path))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        if ($files->isEmpty()) {
            return ['status' => 'ok', 'details' => 'Backup directory exists and ready for use.'];
        }

        $latest = $files->first();
        $time = date('Y-m-d H:i', $latest->getMTime());

        return ['status' => 'ok', 'details' => "Latest backup: {$latest->getFilename()} at {$time}."];
    }

    /**
     * @return array{status:string,details:string}
     */
    private function checkNotifications(): array
    {
        try {
            DB::table('notifications')->count();
            return ['status' => 'ok', 'details' => 'Notifications table is accessible.'];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'details' => $e->getMessage()];
        }
    }

    /**
     * @return array{status:string,details:string}
     */
    private function checkUsers(): array
    {
        $developers = User::query()->where('role', User::ROLE_DEVELOPER)->count();
        $admins = User::query()->where('role', User::ROLE_ADMIN)->count();
        $entries = User::query()->where('role', User::ROLE_DATA_ENTRY)->count();

        return [
            'status' => 'ok',
            'details' => "Developers: {$developers}, Admins: {$admins}, Data Entry: {$entries}.",
        ];
    }
}
