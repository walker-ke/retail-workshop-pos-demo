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
                abort(403, 'Account and shop settings are locked in this demo.');
            }
            if ($request->user()->isTopAdmin() && !$request->is('switch-shop', 'logout')) {
                abort(403, 'The owner account can only view records. Sign in as manager to try making changes.');
            }
        }
        if (!$request->isMethodSafe() && $request->user()) {
            $key = 'demo-write:'.$request->ip().':'.$request->user()->id;
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 120)) abort(429, 'Too many changes at once. Wait a minute, then try again.');
            \Illuminate\Support\Facades\RateLimiter::hit($key, 60);
        }
        return $next($request);
    }
}
