<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserSessionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if (! $user) {
            return $response;
        }

        $sessionId = $request->session()->getId();

        UserSession::query()
            ->where('user_id', $user->id)
            ->where('session_id', '!=', $sessionId)
            ->update(['is_current' => false]);

        UserSession::updateOrCreate(
            ['user_id' => $user->id, 'session_id' => $sessionId],
            [
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'last_activity_at' => now(),
                'is_current' => true,
            ]
        );

        return $response;
    }
}
