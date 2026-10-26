<?php
namespace App\Console\Commands;
use App\Services\BackupService;
use Illuminate\Console\Command;
class VerifyBackupsCommand extends Command
{
    protected $signature = 'backups:verify {file?}';
    protected $description = 'Test backup restoration in an isolated temporary database';
    public function handle(BackupService $backups)
    {
        $file = $this->argument('file') ?: basename($backups->files()[0] ?? '');
        if (!$file) { $this->error('No backup available.'); return 1; }
        try { $counts = $backups->verify($file); $this->info('Verified '.count($counts).' tables and uploaded file checksums. Live data unchanged.'); return 0; }
        catch (\Throwable $e) { $this->error($e->getMessage()); return 1; }
    }
}
