<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);
        $response->setCharset('UTF-8');

        $contentType = $response->headers->get('Content-Type');
        if ($contentType) {
            $lowerContentType = strtolower($contentType);
            if (str_starts_with($lowerContentType, 'text/') || str_contains($lowerContentType, 'text/html')) {
                if (str_contains($lowerContentType, 'charset=')) {
                    $contentType = preg_replace('/;\\s*charset=.+$/i', '; charset=UTF-8', $contentType) ?? $contentType;
                } else {
                    $contentType .= '; charset=UTF-8';
                }
                $response->headers->set('Content-Type', $contentType);
            }
        } else {
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
