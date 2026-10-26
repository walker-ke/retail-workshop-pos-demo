<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DemoRestrictions
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('demo.enabled')) return $next($request);
        // Deny capabilities at the server, including unauthenticated callbacks.
        if ($request->is('backup', 'backup/*')) abort(404);
        if (!$request->isMethodSafe() && $request->user()) {
            if ($request->is('add-user', 'update-user/*', 'reset-password/*', 'delete-user', 'receipt-settings', 'receipt-settings/*',
                'add-shop', 'update-shop/*', 'delete-shop', 'add-payment-type', 'update-payment-type/*', 'delete-payment-type', 'import-products')) {
                abort(403, 'Administration is disabled in this synthetic demo.');
            }
            if ($request->user()->isTopAdmin() && !$request->is('switch-shop', 'logout')) {
                abort(403, 'The demo owner account is read-only. Try the demo manager for sample workflows.');
            }
        }
        if (!$request->isMethodSafe() && $request->user()) {
            $key = 'demo-write:'.$request->ip().':'.$request->user()->id;
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 120)) abort(429, 'Please slow down in the shared demo.');
            \Illuminate\Support\Facades\RateLimiter::hit($key, 60);
        }
        return $next($request);
    }
}
