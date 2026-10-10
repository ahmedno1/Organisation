<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestPerformanceLog
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('app.perf_log', false)) {
            return $next($request);
        }

        $start = microtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;
        $maxQueryMs = 0.0;

        DB::listen(function ($query) use (&$queryCount, &$queryTimeMs, &$maxQueryMs): void {
            $queryCount++;
            $queryTimeMs += (float) $query->time;
            if ((float) $query->time > $maxQueryMs) {
                $maxQueryMs = (float) $query->time;
            }
        });

        $response = $next($request);

        $durationMs = (microtime(true) - $start) * 1000;
        $thresholdMs = (int) config('app.perf_log_threshold_ms', 500);
        $slowQueryMs = (int) config('app.perf_log_slow_query_ms', 50);

        if ($durationMs >= $thresholdMs || $maxQueryMs >= $slowQueryMs) {
            Log::channel('single')->warning('perf.request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
                'duration_ms' => round($durationMs, 1),
                'query_count' => $queryCount,
                'query_time_ms' => round($queryTimeMs, 1),
                'max_query_ms' => round($maxQueryMs, 1),
                'user_id' => $request->user()?->id,
            ]);
        }

        return $response;
    }
}
