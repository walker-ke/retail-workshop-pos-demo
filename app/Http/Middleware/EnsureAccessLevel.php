<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;

class EnsureAccessLevel
{
    public function handle(Request $request, Closure $next, string $operationKey)
    {
        $user = Auth::user();

        if ($user && $user->hasAccess($operationKey)) {
            return $next($request);
        }

        Alert::error('Access Denied', 'Your role cannot access this section. Ask the Owner / Admin.');

        // Redirect somewhere that is never itself access-gated, so a denial can
        // never create a redirect loop (e.g. when "dashboard" itself is restricted).
        return redirect()->route('pos');
    }
}
