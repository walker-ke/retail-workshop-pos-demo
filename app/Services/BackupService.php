<?php
namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupService
{
    public function directory(): string { return config('backup.directory'); }
    public function status(): array
    {
        $path = $this->directory().'/status.json';
        return is_file($path) ? json_decode(file_get_contents($path), true) ?? [] : [];
    }
    private function updateStatus(array $values): void
    {
        $path = $this->directory().'/status.json';
        $tmp = $path.'.tmp';
        file_put_contents($tmp, json_encode(array_merge($this->status(), $values), JSON_PRETTY_PRINT), LOCK_EX);
        chmod($tmp, 0600); rename($tmp, $path);
    }
    public function files(): array
    {
        $files = glob($this->directory().'/backup-*.bbbackup') ?: [];
        rsort($files); return $files;
    }
    public function path(string $file): string
    {
        if (!preg_match('/^backup-\d{8}-\d{6}-[a-f0-9]{8}-(daily|manual|pre-import|pre-update|pre-restore)\.bbbackup$/D', $file)) abort(404);
        $path = $this->directory().'/'.$file;
        if (!is_file($path) || is_link($path)) abort(404);
        return $path;
    }
    private function key(bool $create = false): string
    {
        $path = config('backup.key_path');
        if (!is_file($path) && $create) {
            if ($this->files()) throw new RuntimeException('Recovery key is missing. Restore the original key before creating more backups.');
            $handle = fopen($path, 'x');
            if (!$handle) throw new RuntimeException('Cannot create encryption key.');
            chmod($path, 0600); fwrite($handle, random_bytes(32)); fclose($handle);
        }
        if (!is_file($path)) throw new RuntimeException('Backup encryption key is missing.');
        $key = file_get_contents($path);
        if (str_starts_with($key, 'RETAILDEMO_KEY_V1:')) $key = base64_decode(substr(trim($key), strlen('RETAILDEMO_KEY_V1:')), true);
        if (!is_string($key) || strlen($key) !== 32) throw new RuntimeException('Invalid recovery key.');
        return $key;
    }
    private function locked(callable $operation)
    {
        if (!is_dir($this->directory())) mkdir($this->directory(), 0700, true);
        chmod($this->directory(), 0700);
        $lock = fopen($this->directory().'/.lock', 'c'); chmod($this->directory().'/.lock', 0600);
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new RuntimeException('Another backup or restore is already running.'); }
        $tmp = sys_get_temp_dir().'/retaildemo-backup-'.bin2hex(random_bytes(8));
        mkdir($tmp, 0700);
        try { return $operation($tmp); }
        finally { File::deleteDirectory($tmp); flock($lock, LOCK_UN); fclose($lock); }
    }
    private function credentials(string $tmp): array
    {
        if (config('database.default') !== 'mysql') throw new RuntimeException('Backup currently requires MySQL.');
        $db = config('database.connections.mysql');
        $escape = fn ($v) => '"'.str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], (string) $v).'"';
        $path = $tmp.'/mysql.cnf';
        $text = "[client]\n";
        foreach (['host', 'port', 'username', 'password', 'unix_socket'] as $field) {
            $name = ['username' => 'user', 'unix_socket' => 'socket'][$field] ?? $field;
            if (!empty($db[$field])) $text .= $name.'='.$escape($db[$field])."\n";
        }
        file_put_contents($path, $text); chmod($path, 0600);
        return [$path, $db['database']];
    }
    private function run(array $args, $input = null): string
    {
        $process = new Process($args); $process->setTimeout(900);
        if ($input !== null) $process->setInput($input);
        $process->run();
        if (!$process->isSuccessful()) {
            \Illuminate\Support\Facades\Log::warning('Backup tool failed', ['tool' => basename($args[0]), 'diagnostic' => substr(trim(collect(preg_split('/\R/', trim($process->getErrorOutput())))->last()), 0, 500)]);
            throw new RuntimeException('Backup database/archive operation failed ('.basename($args[0]).'). Check database privileges, disk space and server logs.');
        }
        return $process->getOutput();
    }
    public function create(string $reason = 'manual'): string
    {
        if (config('demo.enabled')) throw new RuntimeException('Recovery operations are disabled in demo mode.');
        return $this->locked(fn ($tmp) => $this->snapshot($tmp, $reason));
    }
    private function snapshot(string $tmp, string $reason): string
    {
        if (!in_array($reason, ['daily', 'manual', 'pre-import', 'pre-update', 'pre-restore'])) throw new RuntimeException('Invalid backup reason.');
        $partial = null;
        try {
            $key = $this->key(true);
            [$credentials, $database] = $this->credentials($tmp);
            $sql = $tmp.'/database.sql';
            touch($sql); chmod($sql, 0600);
            $this->run(['mysqldump', '--defaults-extra-file='.$credentials, '--single-transaction', '--quick', '--routines', '--triggers', '--events', '--hex-blob', '--result-file='.$sql, $database]);
            if (filesize($sql) === 0) throw new RuntimeException('Database dump was empty.');
            $tables = DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']);
            $tableNames = array_map(fn ($row) => array_values((array) $row)[0], $tables);
            $roots = array_values(array_filter(config('backup.uploads'), fn ($root) => is_dir(base_path($root)) && !is_link(base_path($root))));
            $files = [];
            // Stage uploads so the manifest and archive always describe the same bytes.
            foreach ($roots as $root) {
                foreach (File::allFiles(base_path($root)) as $file) {
                    if ($file->isLink()) throw new RuntimeException('Uploaded files must not contain symbolic links.');
                    $relative = $root.'/'.str_replace('\\', '/', $file->getRelativePathname());
                    File::ensureDirectoryExists(dirname($tmp.'/'.$relative), 0700);
                    if (!copy($file->getPathname(), $tmp.'/'.$relative)) throw new RuntimeException('Cannot stage uploaded file.');
                    chmod($tmp.'/'.$relative, 0600);
                    $files[$relative] = hash_file('sha256', $tmp.'/'.$relative);
                }
                File::ensureDirectoryExists($tmp.'/'.$root, 0700);
            }
            file_put_contents($tmp.'/manifest.json', json_encode(['version' => 1, 'created_at' => now()->toIso8601String(), 'reason' => $reason, 'tables' => $tableNames, 'roots' => $roots, 'files' => (object) $files, 'database_sha256' => hash_file('sha256', $sql)], JSON_PRETTY_PRINT));
            chmod($tmp.'/manifest.json', 0600);
            $archive = $tmp.'/archive.tar.gz';
            touch($archive); chmod($archive, 0600);
            $this->run(array_merge(['tar', '-czf', $archive, '-C', $tmp, 'database.sql', 'manifest.json'], $roots));
            $name = 'backup-'.now(config('backup.timezone'))->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'-'.$reason.'.bbbackup';
            $path = $this->directory().'/'.$name;
            $partial = $path.'.partial';
            (new BackupCipher)->encrypt($archive, $partial, $key);
            if (!rename($partial, $path)) throw new RuntimeException('Cannot finish backup.');
            $this->updateStatus(['last_success' => now()->toIso8601String(), 'last_file' => $name, 'last_error' => null]);
            try { $this->copyExternal($path); }
            catch (\Throwable $e) { $this->updateStatus(['external_status' => $e->getMessage()]); throw $e; }
            $this->prune();
            return $name;
        } catch (\Throwable $e) {
            if ($partial && is_file($partial)) unlink($partial);
            $this->updateStatus(['last_error' => $e->getMessage(), 'last_failure' => now()->toIso8601String()]);
            throw $e;
        }
    }
    private function copyExternal(string $path): void
    {
        $google = app(GoogleDriveBackup::class);
        if ($google->settings()['connected']) {
            $google->upload($path);
            $this->updateStatus(['external_status' => 'Uploaded to Google Drive and checksum verified', 'external_success' => now()->toIso8601String()]);
            return;
        }
        $destination = config('backup.external_path');
        if (!$destination) { $this->updateStatus(['external_status' => 'Not configured — only a local copy exists.']); return; }
        // Never create this directory: a disconnected drive must fail, not write to the local mountpoint.
        if (!is_dir($destination) || !is_writable($destination) || realpath($destination) === realpath($this->directory())) throw new RuntimeException('Local backup saved, but off-computer destination is unavailable.');
        if (config('backup.external_type') !== 'cloud' && stat($destination)['dev'] === stat($this->directory())['dev']) {
            throw new RuntimeException('Local backup saved, but the external drive is not mounted on a separate filesystem.');
        }
        $target = rtrim($destination, '/').'/'.basename($path);
        $partial = $target.'.partial';
        if (!copy($path, $partial)) throw new RuntimeException('Local backup saved, but external copy failed.');
        chmod($partial, 0600);
        if (!hash_equals(hash_file('sha256', $path), hash_file('sha256', $partial))) { unlink($partial); throw new RuntimeException('External backup checksum mismatch.'); }
        rename($partial, $target);
        $this->updateStatus(['external_status' => 'Copied and checksum verified', 'external_success' => now()->toIso8601String()]);
        $this->prune($destination);
    }
    public function retentionKeep(array $files): array
    {
        rsort($files); $keep = []; $seen = ['day' => [], 'week' => [], 'month' => []];
        foreach ($files as $file) {
            if (!preg_match('/^backup-(\d{8})-\d{6}-[a-f0-9]{8}-(daily|manual|pre-import|pre-update|pre-restore)\.bbbackup$/D', basename($file), $m)) continue;
            if (str_starts_with($m[2], 'pre-')) { $keep[] = $file; continue; }
            $date = Carbon::createFromFormat('!Ymd', $m[1]);
            foreach (['day' => ['Y-m-d', 7], 'week' => ['o-W', 4], 'month' => ['Y-m', 12]] as $bucket => [$format, $limit]) {
                $period = $date->format($format);
                if (!isset($seen[$bucket][$period]) && count($seen[$bucket]) < $limit) { $seen[$bucket][$period] = true; $keep[] = $file; }
            }
        }
        return array_unique($keep);
    }
    private function prune(?string $directory = null): void
    {
        $files = glob(($directory ?: $this->directory()).'/backup-*.bbbackup') ?: [];
        foreach (array_diff($files, $this->retentionKeep($files)) as $file) if (!is_link($file)) unlink($file);
    }
    private function extract(string $path, string $tmp): array
    {
        $archive = $tmp.'/restore.tar.gz';
        (new BackupCipher)->decrypt($path, $archive, $this->key());
        $extract = $tmp.'/extracted'; mkdir($extract, 0700);
        $this->run(['python3', base_path('tools/backup_archive.py'), $archive, $extract]);
        return [$extract, json_decode(file_get_contents($extract.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR)];
    }
    private function testDatabase(string $credentials, string $extract, array $manifest): array
    {
        if (empty($manifest['tables']) || !is_array($manifest['tables'])) {
            throw new RuntimeException('Recovery test requires a non-empty database table manifest.');
        }
        $managed = config('backup.test_database');
        if ($managed) return $this->testManagedDatabase($credentials, $extract, $manifest, $managed);
        // Use a separate server, private socket and data directory. No live DB privileges needed.
        $work = dirname($extract).'/test-server'; mkdir($work, 0700);
        $data = $work.'/data';
        $this->run(['mariadb-install-db', '--no-defaults', '--datadir='.$data, '--auth-root-authentication-method=normal', '--skip-test-db']);
        $socket = $work.'/mysql.sock';
        $server = new Process(['mariadbd', '--no-defaults', '--datadir='.$data, '--socket='.$socket,
            '--pid-file='.$work.'/server.pid', '--skip-networking', '--skip-log-bin',
            '--innodb-buffer-pool-size=64M', '--log-error='.$work.'/server.log']);
        $server->setTimeout(null); $server->start();
        $client = ['mysql', '--no-defaults', '--socket='.$socket, '--user=root'];
        try {
            $ready = false;
            for ($attempt = 0; $attempt < 300; $attempt++) {
                if (!$server->isRunning()) {
                    \Illuminate\Support\Facades\Log::warning('Restore-test server failed', ['diagnostic' => is_file($work.'/server.log') ? substr(file_get_contents($work.'/server.log'), -3000) : $server->getErrorOutput()]);
                    throw new RuntimeException('Isolated restore-test server failed to start.');
                }
                if (file_exists($socket)) {
                    $probe = new Process(array_merge($client, ['-e', 'SELECT 1'])); $probe->setTimeout(2); $probe->run();
                    if ($probe->isSuccessful()) { $ready = true; break; }
                }
                usleep(100000);
            }
            if (!$ready) throw new RuntimeException('Isolated restore-test server timed out.');
            $test = 'retaildemo_restore_test';
            $this->run(array_merge($client, ['-e', 'CREATE DATABASE `'.$test.'`']));
            $input = fopen($extract.'/database.sql', 'rb');
            try { $this->run(array_merge($client, [$test]), $input); } finally { fclose($input); }
            $tables = preg_split('/\R/', trim($this->run(array_merge($client, ['-N', '-B', $test, '-e', "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"]))));
            $actual = array_map(fn ($line) => explode("\t", $line)[0], $tables);
            $expected = $manifest['tables']; sort($actual); sort($expected);
            if ($actual !== $expected) throw new RuntimeException('Restore test did not recreate all database tables.');
            $counts = [];
            foreach ($expected as $table) {
                $quoted = str_replace('`', '``', $table);
                $check = $this->run(array_merge($client, ['-N', '-B', $test, '-e', 'CHECK TABLE `'.$quoted.'`']));
                if (!str_contains($check, "status\tOK")) throw new RuntimeException('Restored table integrity check failed.');
                $counts[$table] = (int) trim($this->run(array_merge($client, ['-N', '-B', $test, '-e', 'SELECT COUNT(*) FROM `'.$quoted.'`'])));
            }
            return $counts;
        } finally { $server->stop(5); }
    }
    private function assertManagedTarget(string $test): void
    {
        $live = config('database.connections.mysql.database');
        if (!preg_match('/^[A-Za-z0-9_-]+_restore_test$/D', $test) || strcasecmp($test, $live) === 0) {
            throw new RuntimeException('Recovery test database must be separate from live data and end in _restore_test.');
        }
    }
    private function testManagedDatabase(string $credentials, string $extract, array $manifest, string $test): array
    {
        $this->assertManagedTarget($test);
        $client = ['mysql', '--defaults-extra-file='.$credentials, '-N', '-B', $test];
        $list = function () use ($client) {
            $result = trim($this->run(array_merge($client, ['-e', 'SHOW FULL TABLES'])));
            return $result === '' ? [] : array_map(fn ($line) => explode("\t", $line), preg_split('/\R/', $result));
        };
        if ($list() !== []) throw new RuntimeException('Recovery test database is not empty. Refusing to change existing data.');
        try {
            $input = fopen($extract.'/database.sql', 'rb');
            try { $this->run(array_merge(['mysql', '--defaults-extra-file='.$credentials], [$test]), $input); }
            finally { fclose($input); }
            $tables = $list();
            $actual = array_column($tables, 0); $expected = $manifest['tables'];
            sort($actual); sort($expected);
            if ($actual !== $expected) throw new RuntimeException('Restore test did not recreate all database tables.');
            $counts = [];
            foreach ($expected as $table) {
                $quoted = str_replace('`', '``', $table);
                $check = $this->run(array_merge($client, ['-e', 'CHECK TABLE `'.$quoted.'`']));
                if (!str_contains($check, "status\tOK")) throw new RuntimeException('Restored table integrity check failed.');
                $counts[$table] = (int) trim($this->run(array_merge($client, ['-e', 'SELECT COUNT(*) FROM `'.$quoted.'`'])));
            }
            return $counts;
        } finally {
            // This database was checked empty before importing. Only test-created objects are removed.
            $this->assertManagedTarget($test);
            $sql = 'SET FOREIGN_KEY_CHECKS=0;';
            foreach ($list() as [$table, $type]) {
                $sql .= 'DROP '.($type === 'VIEW' ? 'VIEW' : 'TABLE').' `'.str_replace('`', '``', $table).'`;';
            }
            $this->run(array_merge($client, ['-e', $sql.'SET FOREIGN_KEY_CHECKS=1;']));
        }
    }
    public function verify(string $file): array
    {
        if (config('demo.enabled')) throw new RuntimeException('Recovery operations are disabled in demo mode.');
        return $this->locked(function ($tmp) use ($file) {
            try {
                [$extract, $manifest] = $this->extract($this->path($file), $tmp);
                [$credentials] = $this->credentials($tmp);
                $counts = $this->testDatabase($credentials, $extract, $manifest);
                $this->updateStatus(['last_verified' => now()->toIso8601String(), 'verified_file' => $file, 'verified_counts' => $counts, 'verification_error' => null]);
                return $counts;
            } catch (\Throwable $e) { $this->updateStatus(['verification_error' => $e->getMessage()]); throw $e; }
        });
    }
    public function restore(string $file): void
    {
        if (config('demo.enabled')) throw new RuntimeException('Recovery operations are disabled in demo mode.');
        $this->locked(function ($tmp) use ($file) {
            [$extract, $manifest] = $this->extract($this->path($file), $tmp);
            [$credentials, $database] = $this->credentials($tmp);
            $this->testDatabase($credentials, $extract, $manifest);
            if (!in_array(config('session.driver'), ['file', 'database'], true)) throw new RuntimeException('Restore requires file or database sessions so existing logins can be invalidated.');
            Artisan::call('down');
            $liveWrites = false;
            try {
                // Stop new sales before making the recovery safety snapshot.
                $safety = $tmp.'/safety'; mkdir($safety, 0700);
                $safetyFile = $this->snapshot($safety, 'pre-restore');
                // Prove the safety copy can be decrypted and restored before overwriting live records.
                $safetyCheck = $tmp.'/safety-check'; mkdir($safetyCheck, 0700);
                [$safetyExtract, $safetyManifest] = $this->extract($this->path($safetyFile), $safetyCheck);
                $this->testDatabase($credentials, $safetyExtract, $safetyManifest);
                $liveWrites = true;
                $input = fopen($extract.'/database.sql', 'rb');
                try { $this->run(['mysql', '--defaults-extra-file='.$credentials, $database], $input); } finally { fclose($input); }
                foreach ($manifest['roots'] as $root) {
                    if (!in_array($root, config('backup.uploads'), true) || is_link(base_path($root))) throw new RuntimeException('Invalid restore upload location.');
                    File::deleteDirectory(base_path($root));
                    if (!File::copyDirectory($extract.'/'.$root, base_path($root))) throw new RuntimeException('Upload restore failed.');
                }
                if (config('session.driver') === 'file') File::cleanDirectory(config('session.files'));
                else DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->delete();
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'remember_token')) DB::table('users')->update(['remember_token' => null]);
                Artisan::call('up');
                $this->updateStatus(['last_restore' => now()->toIso8601String(), 'restored_file' => $file, 'restore_error' => null]);
            } catch (\Throwable $e) {
                if (!$liveWrites) Artisan::call('up');
                $this->updateStatus(['restore_error' => $liveWrites
                    ? 'Restore failed. Site remains in maintenance mode. Recover using the pre-restore backup and backups:restore command.'
                    : 'Restore stopped before changing live data: '.$e->getMessage()]);
                throw $e;
            }
        });
    }
}
