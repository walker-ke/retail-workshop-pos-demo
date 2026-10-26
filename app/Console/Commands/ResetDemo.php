<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class ResetDemo extends Command
{
    protected $signature = 'demo:reset {--force : Confirm replacement of the disposable demo dataset}';
    protected $description = 'Reset only a synthetic database whose name ends in _demo';
    public function handle(): int
    {
        $connection = config('database.default');
        $name = config("database.connections.$connection.database");
        if (!config('demo.enabled') || !is_string($name) || !preg_match('/^[a-zA-Z0-9_]+_demo$/D',$name) || !$this->option('force')) {
            $this->error('Requires DEMO_MODE=true, a dedicated database ending in _demo, and --force.');
            return self::FAILURE;
        }
        $result = $this->call('migrate:fresh', ['--seed'=>true,'--force'=>true]);
        if ($result === self::SUCCESS) {
            \Illuminate\Support\Facades\File::cleanDirectory(storage_path('framework/sessions'));
        }
        return $result;
    }
}
