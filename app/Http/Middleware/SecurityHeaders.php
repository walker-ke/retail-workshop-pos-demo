<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'");
        if ($request->isSecure()) $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        // Business records and recovery material must not survive in shared caches.
        $response->headers->set('Cache-Control', 'private, no-store');
        if (config('demo.enabled')) $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return $response;
    }
}
