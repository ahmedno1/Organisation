<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED_LOCALES = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get('locale', config('app.locale', 'ar'));

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = config('app.locale', 'ar');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
