<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceDeveloperTwoFactorSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests() || app()->environment('testing')) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user || ! $user->isDeveloper()) {
            return $next($request);
        }

        if ($user->hasTotpEnabled()) {
            return $next($request);
        }

        $allowed = [
            'logout',
            'two_factor.setup',
            'two_factor.setup.store',
        ];

        if (in_array((string) $request->route()?->getName(), $allowed, true)) {
            return $next($request);
        }

        return redirect()
            ->route('two_factor.setup')
            ->with('status', 'You must enable two-factor authentication before continuing.');
    }
}
