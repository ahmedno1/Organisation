<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_logger_redacts_sensitive_meta_keys(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/security-test', 'GET');
        $request->server->set('HTTP_USER_AGENT', str_repeat('a', 1200));
        $request->setUserResolver(fn () => $user);

        ActivityLogger::log($request, 'security.test', null, [
            'national_id' => '123456789',
            'nested' => [
                'email' => 'user@example.com',
                'token' => 'abc',
            ],
            'notes' => str_repeat('x', 700),
        ]);

        $log = ActivityLog::query()->firstOrFail();
        $meta = $log->meta ?? [];

        $this->assertSame('[REDACTED]', $meta['national_id'] ?? null);
        $this->assertSame('[REDACTED]', $meta['nested']['email'] ?? null);
        $this->assertSame('[REDACTED]', $meta['nested']['token'] ?? null);
        $this->assertLessThanOrEqual(515, strlen((string) ($meta['notes'] ?? '')));
        $this->assertLessThanOrEqual(1027, strlen((string) ($log->user_agent ?? '')));
    }
}

