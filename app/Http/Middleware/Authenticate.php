<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request, $guards);
        $user = $this->auth->user();
        if ($user->active === false) {
            $this->auth->guard()->logoutCurrentDevice();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            throw new \Illuminate\Auth\AuthenticationException('This account is disabled.', $guards, $this->redirectTo($request));
        }
        abort_unless(array_key_exists((int) $user->access_level, \App\Models\User::roles()), 403, 'Ask the owner to assign a valid access level.');
        abort_if((int) $user->access_level !== \App\Models\User::OWNER && !$user->activeShopId(), 403, 'Ask the owner to assign your shop.');
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
