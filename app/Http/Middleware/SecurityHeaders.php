<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Header keamanan dasar untuk semua halaman dan API. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        if ($request->isSecure() && app()->isProduction()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
