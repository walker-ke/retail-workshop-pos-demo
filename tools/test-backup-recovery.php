<?php
// Full recovery rehearsal against disposable files and a separate server only.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$originalBase = base_path();
$work = sys_get_temp_dir().'/bb-rehearsal-'.bin2hex(random_bytes(6));
mkdir($work, 0700);
$server = null;
$run = function (array $args) {
    $process = new Symfony\Component\Process\Process($args);
    $process->setTimeout(60); $process->mustRun(); return $process->getOutput();
};
$assert = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
try {
    $run(['mariadb-install-db', '--no-defaults', '--datadir='.$work.'/db', '--auth-root-authentication-method=normal', '--skip-test-db']);
    $socket = $work.'/mysql.sock';
    $server = new Symfony\Component\Process\Process(['mariadbd', '--no-defaults', '--datadir='.$work.'/db', '--socket='.$socket, '--pid-file='.$work.'/pid', '--skip-networking', '--skip-log-bin', '--log-error='.$work.'/server.log']);
    $server->setTimeout(null); $server->start();
    $client = ['mysql', '--no-defaults', '--socket='.$socket, '--user=root'];
    $ready = false;
    for ($i=0; $i<300; $i++) {
        if (!$server->isRunning()) throw new RuntimeException('Rehearsal server failed');
        if (file_exists($socket)) {
            try { $run(array_merge($client, ['-e', 'SELECT 1'])); $ready = true; break; } catch (Throwable $e) {}
        }
        usleep(100000);
    }
    $assert($ready, 'Rehearsal server timed out');
    $run(array_merge($client, ['-e', 'CREATE DATABASE rehearsal; USE rehearsal; CREATE TABLE users(id INT PRIMARY KEY, remember_token VARCHAR(100)); INSERT INTO users VALUES(1,"old-token"); CREATE TABLE sales(id INT PRIMARY KEY, total DECIMAL(10,2)); INSERT INTO sales VALUES(1,100.00);']));
    $sandbox = $work.'/app';
    Illuminate\Support\Facades\File::ensureDirectoryExists($sandbox.'/storage/framework/sessions', 0700);
    Illuminate\Support\Facades\File::ensureDirectoryExists($sandbox.'/public/images', 0700);
    Illuminate\Support\Facades\File::ensureDirectoryExists($sandbox.'/tools', 0700);
    copy($originalBase.'/tools/backup_archive.py', $sandbox.'/tools/backup_archive.py');
    file_put_contents($sandbox.'/public/images/example.txt', 'original-upload');
    $app->setBasePath($sandbox); $app->useStoragePath($sandbox.'/storage');
    config(['database.default'=>'mysql', 'database.connections.mysql.host'=>'localhost', 'database.connections.mysql.unix_socket'=>$socket, 'database.connections.mysql.database'=>'rehearsal', 'database.connections.mysql.username'=>'root', 'database.connections.mysql.password'=>'', 'backup.directory'=>$sandbox.'/storage/app/backups', 'backup.key_path'=>$sandbox.'/storage/app/backup-key', 'backup.external_path'=>null, 'backup.uploads'=>['public/images'], 'session.driver'=>'file', 'session.files'=>$sandbox.'/storage/framework/sessions']);
    Illuminate\Support\Facades\DB::purge('mysql');
    $backups = new App\Services\BackupService;
    $snapshot = $backups->create('manual');
    $keyFile = config('backup.key_path');
    file_put_contents($keyFile, 'RETAILDEMO_KEY_V1:'.base64_encode(file_get_contents($keyFile))."\n");
    Illuminate\Support\Facades\DB::table('sales')->insert(['id'=>2,'total'=>250]);
    file_put_contents($sandbox.'/public/images/example.txt', 'changed-upload');
    file_put_contents($sandbox.'/public/images/extra.txt', 'extra-upload');
    file_put_contents($sandbox.'/storage/framework/sessions/old-session', 'old-session');
    $backups->restore($snapshot);
    $assert(Illuminate\Support\Facades\DB::table('sales')->count() === 1, 'Sales were not restored');
    $assert(file_get_contents($sandbox.'/public/images/example.txt') === 'original-upload', 'Upload was not restored');
    $assert(!file_exists($sandbox.'/public/images/extra.txt'), 'Extra upload remained');
    $assert(!file_exists($sandbox.'/storage/framework/sessions/old-session'), 'Old session remained');
    $assert(Illuminate\Support\Facades\DB::table('users')->value('remember_token') === null, 'Remember login remained');
    $safety = array_values(array_filter($backups->files(), fn($p)=>str_ends_with($p,'-pre-restore.bbbackup')));
    $assert(count($safety) === 1, 'Pre-restore safety backup missing');
    $counts = $backups->verify(basename($safety[0]));
    $assert($counts['sales'] === 2, 'Safety backup did not preserve newer sales');
    $assert(!$app->isDownForMaintenance(), 'Maintenance mode was not cleared');
    echo "PASS: full disposable restore, uploads, login invalidation and pre-restore safety recovery. Live POS untouched.\n";
} finally {
    Illuminate\Support\Facades\DB::disconnect('mysql');
    if ($server) $server->stop(5);
    Illuminate\Support\Facades\File::deleteDirectory($work);
}
