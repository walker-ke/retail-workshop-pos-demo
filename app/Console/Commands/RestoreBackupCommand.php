<?php
namespace App\Console\Commands;
use App\Services\BackupService;
use Illuminate\Console\Command;
class RestoreBackupCommand extends Command
{
    protected $signature = 'backups:restore {file}';
    protected $description = 'Restore a verified backup after explicit confirmation and safety backup';
    public function handle(BackupService $backups)
    {
        if ($this->ask('This replaces live sales, stock, users and uploaded files. Type RESTORE to proceed') !== 'RESTORE') return 1;
        try { $backups->restore($this->argument('file')); $this->info('Restored successfully.'); return 0; }
        catch (\Throwable $e) { $this->error($e->getMessage()); return 1; }
    }
}
