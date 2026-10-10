<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\ActivityLogger;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasTable('api_keys')) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $provided = trim((string) $request->header('X-API-KEY', ''));
        if ($provided === '') {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = ApiKey::query()
            ->where('key_hash', ApiKey::hashKey($provided))
            ->where('is_active', true)
            ->first();

        if (! $apiKey) {
            return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $permission = $this->resolveRequiredPermission($request);
        $permissions = $apiKey->permissions ?? [];
        if ($permission !== null && ! in_array($permission, $permissions, true)) {
            return new JsonResponse(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $limitKey = 'api-key:'.$apiKey->id;
        if (RateLimiter::tooManyAttempts($limitKey, 120)) {
            return new JsonResponse(['message' => 'Too Many Requests'], Response::HTTP_TOO_MANY_REQUESTS);
        }
        RateLimiter::hit($limitKey, 60);

        $apiKey->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->save();

        $request->attributes->set('api_key', $apiKey);

        ActivityLogger::log($request, 'api.request', $apiKey, [
            'method' => $request->method(),
            'path' => $request->path(),
            'required_permission' => $permission,
        ]);

        return $next($request);
    }

    private function resolveRequiredPermission(Request $request): ?string
    {
        if ($request->isMethod('GET') && $request->route('family')) {
            return 'families.show';
        }

        if ($request->isMethod('GET') && $request->is('api/v1/families')) {
            return 'families.read';
        }

        return null;
    }
}
