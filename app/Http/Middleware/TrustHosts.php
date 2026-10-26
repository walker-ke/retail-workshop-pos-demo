<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts()
    {
        return [
            '^'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost', '/').'$',
        ];
    }
}
