<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (config('demo.enabled') && !app()->runningUnitTests()) {
            $connection = config('database.default');
            $database = config("database.connections.$connection.database");
            if (!is_string($database) || !preg_match('/^[a-zA-Z0-9_]+_demo$/D', $database)) {
                throw new \RuntimeException('Demo mode requires a dedicated database name ending in _demo.');
            }
        }

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandStarting::class, function ($event) {
            if (!config('demo.enabled') && !app()->runningUnitTests() && in_array($event->command, ['migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback'], true)) {
                static $backedUp = false;
                if (!$backedUp) {
                    app(\App\Services\BackupService::class)->create('pre-update');
                    $backedUp = true;
                }
            }
        });
        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            if (auth()->check() && auth()->user()->hasAccess('backup')) {
                $status = app(\App\Services\BackupService::class)->status();
                                $stale = empty($status['last_success']) || \Carbon\Carbon::parse($status['last_success'])->lt(now()->subHours(36));
                $view->with('backupAttention', $status['last_error'] ?? $status['verification_error'] ?? $status['restore_error'] ?? ($stale ? 'No recent successful backup.' : null));
            }
        });
    }
}
