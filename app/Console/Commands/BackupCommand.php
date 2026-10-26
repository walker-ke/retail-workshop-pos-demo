<?php
namespace App\Console\Commands;
use App\Services\BackupService;
use Illuminate\Console\Command;
class BackupCommand extends Command
{
    protected $signature = 'backups:run {--reason=daily}';
    protected $description = 'Create an encrypted database and uploads backup with retention';
    public function handle(BackupService $backups)
    {
        try { $name = $backups->create($this->option('reason')); $this->info('Saved '.$name); return 0; }
        catch (\Throwable $e) { $this->error($e->getMessage()); return 1; }
    }
}
