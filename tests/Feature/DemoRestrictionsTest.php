<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;

class DemoRestrictionsTest extends TestCase
{
    protected function setUp(): void {parent::setUp();config(['demo.enabled'=>true]);}
    public function test_every_backup_capability_is_unavailable_even_to_demo_owner(): void
    {
        foreach ([null, User::OWNER, User::MANAGER, User::CASHIER] as $role) {
            if ($role !== null) $this->actingAs(new User(['id'=>1,'access_level'=>$role,'shop_id'=>1,'active'=>true]));
            foreach (['/backup','/backup/recovery-key','/%62ackup','/backup/','/backup/download/example.bbbackup','/backup/google-callback?code=anything'] as $path) $this->get($path)->assertNotFound();
            foreach (['/backup/create','/backup/verify','/backup/restore','/backup/google-setup','/backup/google-connect','/backup/google-disconnect'] as $path) $this->post($path)->assertNotFound();
            $this->delete('/backup/delete')->assertNotFound();
        }
    }
    public function test_owner_is_read_only_and_administration_is_blocked(): void
    {
        $this->actingAs(new User(['id'=>1,'access_level'=>User::OWNER,'shop_id'=>1,'active'=>true]));
        foreach (['/add-cart','/add-transaction','/update-cart/anything','/workshop/payments','/hubs'] as $path) {
            $this->post($path)->assertForbidden();
        }
        foreach ([User::OWNER,User::MANAGER] as $role) {
            $this->actingAs(new User(['id'=>1,'access_level'=>$role,'shop_id'=>1,'active'=>true]));
            foreach (['/add-user','/update-user/1','/reset-password/1','/add-shop','/receipt-settings/1','/import-products'] as $path) $this->post($path)->assertForbidden();
        }
    }
    public function test_sensitive_permissions_are_denied_before_owner_shortcut(): void
    {
        $owner=new User(['access_level'=>User::OWNER]);
        foreach (['backup','users_security','shops','payment_types'] as $permission) $this->assertFalse($owner->hasAccess($permission));
        $this->assertTrue($owner->hasAccess('dashboard'));
        $this->assertTrue((new User(['access_level'=>User::MANAGER]))->hasAccess('workshop'));
    }
    public function test_recovery_service_cannot_run_from_cli_in_demo_mode(): void
    {
        foreach (['create'=>[], 'verify'=>['anything'], 'restore'=>['anything']] as $method=>$args) {
            try {(new \App\Services\BackupService)->$method(...$args);$this->fail('Recovery service allowed');}
            catch (\RuntimeException $error) {$this->assertStringContainsString('disabled in demo mode',$error->getMessage());}
        }
    }
    public function test_demo_reset_rejects_non_demo_database_and_requires_confirmation(): void
    {
        config(['database.default'=>'mysql','database.connections.mysql.database'=>'important_records']);
        $this->artisan('demo:reset',['--force'=>true])->assertExitCode(1);
        config(['database.connections.mysql.database'=>'retail_workshop_demo']);
        $this->artisan('demo:reset')->assertExitCode(1);
        config(['demo.enabled'=>false]);
        $this->artisan('demo:reset',['--force'=>true])->assertExitCode(1);
    }
    public function test_demo_startup_refuses_an_unrelated_database_name(): void
    {
        $process = new \Symfony\Component\Process\Process(
            [PHP_BINARY, base_path('artisan'), 'about'], base_path(),
            ['DEMO_MODE'=>'true','APP_ENV'=>'local','DB_DATABASE'=>'important_records']
        );
        $process->run();
        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('dedicated database name ending in _demo', $process->getOutput().$process->getErrorOutput());
    }
}
