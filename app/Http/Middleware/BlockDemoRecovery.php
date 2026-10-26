<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class BlockDemoRecovery
{
    public function handle(Request $request, Closure $next)
    {
        $path = trim($request->decodedPath(), '/');
        if (config('demo.enabled') && ($path === 'backup' || str_starts_with($path,'backup/'))) abort(404);
        return $next($request);
    }
}
