<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\BackupService;
use Tests\TestCase;
class BackupTest extends TestCase
{
    public function test_non_admins_cannot_read_or_modify_backups_or_recovery_keys(): void
    {
        foreach ([User::CASHIER, User::MANAGER] as $role) {
            $this->actingAs(new User(['access_level' => $role, 'shop_id' => 1]));
            foreach (['/backup', '/backup/recovery-key', '/backup/download/anything.bbbackup'] as $url) $this->get($url)->assertRedirect(route('pos'));
            foreach (['/backup/create', '/backup/verify', '/backup/restore'] as $url) $this->post($url, ['file' => 'anything', 'confirmation' => 'RESTORE'])->assertRedirect(route('pos'));
            $this->delete('/backup/delete', ['file' => 'anything', 'confirmation' => 'DELETE'])->assertRedirect(route('pos'));
        }
    }
    public function test_owner_can_download_recovery_key_and_backup_with_private_headers(): void
    {
        $this->actingAs(new User(['access_level' => User::OWNER]));
        $key = tempnam(sys_get_temp_dir(), 'test-key-');
        file_put_contents($key, random_bytes(32));
        config(['backup.key_path' => $key]);
        try {
            $response = $this->get('/backup/recovery-key')->assertOk();
            $this->assertStringStartsWith('RETAILDEMO_KEY_V1:', $response->getContent());
            $this->assertSame(file_get_contents($key), base64_decode(substr(trim($response->getContent()), strlen('RETAILDEMO_KEY_V1:')), true));
            $this->assertStringContainsString('retaildemo-backup-recovery.key', $response->headers->get('Content-Disposition'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $service = \Mockery::mock(BackupService::class);
            $service->shouldReceive('path')->once()->with('test.bbbackup')->andReturn($key);
            $this->app->instance(BackupService::class, $service);
            $this->get('/backup/download/test.bbbackup')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        } finally { unlink($key); }
    }
    public function test_restore_requires_explicit_confirmation_before_service_is_called(): void
    {
        $this->actingAs(new User(['access_level' => User::OWNER]));
        $service = \Mockery::mock(BackupService::class); $service->shouldNotReceive('restore');
        $this->app->instance(BackupService::class, $service);
        $this->post('/backup/restore', ['file' => 'anything'])->assertSessionHasErrors('confirmation');
        $this->post('/backup/restore', ['file' => 'anything', 'confirmation' => 'yes'])->assertSessionHasErrors('confirmation');
    }
    public function test_managed_recovery_target_rejects_live_database_and_unsafe_names_before_running_tools(): void
    {
        $method = new \ReflectionMethod(BackupService::class, 'testManagedDatabase');
        $method->setAccessible(true);
        foreach (['live_restore_test', 'production', 'test_restore_test;DROP DATABASE production'] as $target) {
            config(['database.connections.mysql.database' => 'live_restore_test']);
            try {
                $method->invoke(new BackupService, '/missing-credentials', '/missing-archive', [], $target);
                $this->fail('Unsafe recovery target accepted.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('must be separate from live data', $e->getMessage());
            }
        }
    }
    public function test_empty_recovery_manifest_is_rejected_before_database_tools_run(): void
    {
        $method = new \ReflectionMethod(BackupService::class, 'testDatabase');
        $method->setAccessible(true);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('non-empty database table manifest');
        $method->invoke(new BackupService, '/missing-credentials', '/missing-archive', ['tables' => []]);
    }
    public function test_retention_preserves_distinct_daily_weekly_monthly_snapshots_and_safety_copy(): void
    {
        $files = [];
        for ($day = 1; $day <= 31; $day++) {
            foreach (['090000', '220000'] as $time) $files[] = sprintf('backup-202610%02d-%s-aaaaaaaa-daily.bbbackup', $day, $time);
        }
        for ($month = 1; $month <= 9; $month++) $files[] = sprintf('backup-2026%02d28-220000-aaaaaaaa-daily.bbbackup', $month);
        $files[] = 'backup-20250101-220000-aaaaaaaa-pre-restore.bbbackup';
        $files[] = 'backup-20241031-220000-aaaaaaaa-daily.bbbackup';
        $keep = (new BackupService)->retentionKeep($files);
        for ($day = 25; $day <= 31; $day++) $this->assertContains(sprintf('backup-202610%02d-220000-aaaaaaaa-daily.bbbackup', $day), $keep);
        $this->assertContains('backup-20261018-220000-aaaaaaaa-daily.bbbackup', $keep);
        $this->assertContains('backup-20261011-220000-aaaaaaaa-daily.bbbackup', $keep);
        $this->assertContains('backup-20260128-220000-aaaaaaaa-daily.bbbackup', $keep);
        $this->assertContains('backup-20250101-220000-aaaaaaaa-pre-restore.bbbackup', $keep);
        $this->assertNotContains('backup-20261030-090000-aaaaaaaa-daily.bbbackup', $keep);
        $this->assertNotContains('backup-20261001-220000-aaaaaaaa-daily.bbbackup', $keep);
    }
}
