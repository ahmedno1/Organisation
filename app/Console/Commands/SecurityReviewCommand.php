<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SecurityReviewCommand extends Command
{
    protected $signature = 'security:review';
    protected $description = 'Run baseline security checks and print findings';

    public function handle(): int
    {
        $checks = [
            [
                'label' => 'APP_KEY is configured',
                'ok' => filled((string) config('app.key')),
                'hint' => 'Set APP_KEY in environment before production deploy.',
            ],
            [
                'label' => 'APP_DEBUG is disabled',
                'ok' => (bool) config('app.debug') === false,
                'hint' => 'Set APP_DEBUG=false in production.',
            ],
            [
                'label' => 'Session cookie is HTTP only',
                'ok' => (bool) config('session.http_only', false) === true,
                'hint' => 'Set SESSION_HTTP_ONLY=true.',
            ],
            [
                'label' => 'Session cookie uses secure flag',
                'ok' => (bool) config('session.secure', false) === true,
                'hint' => 'Set SESSION_SECURE_COOKIE=true and serve over HTTPS.',
            ],
            [
                'label' => 'SameSite session policy is strict or lax',
                'ok' => in_array(strtolower((string) config('session.same_site', '')), ['strict', 'lax'], true),
                'hint' => 'Set SESSION_SAME_SITE=lax (or strict).',
            ],
            [
                'label' => 'HTTPS URL is configured',
                'ok' => str_starts_with(strtolower((string) config('app.url', '')), 'https://'),
                'hint' => 'Set APP_URL to an https:// endpoint.',
            ],
            [
                'label' => 'Activity log table exists',
                'ok' => Schema::hasTable('activity_logs'),
                'hint' => 'Run migrations to create activity_logs.',
            ],
            [
                'label' => 'API keys table exists',
                'ok' => Schema::hasTable('api_keys'),
                'hint' => 'Run migrations to create api_keys.',
            ],
        ];

        $failed = 0;
        foreach ($checks as $check) {
            if ($check['ok']) {
                $this->line('[OK] '.$check['label']);
                continue;
            }

            $failed++;
            $this->warn('[WARN] '.$check['label']);
            $this->line('       '.$check['hint']);
        }

        if ($failed === 0) {
            $this->info('Security review passed with no warnings.');
            return self::SUCCESS;
        }

        $this->warn("Security review completed with {$failed} warning(s).");
        return self::FAILURE;
    }
}

