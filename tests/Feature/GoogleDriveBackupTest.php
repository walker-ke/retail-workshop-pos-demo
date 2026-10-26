<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\GoogleDriveBackup;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class GoogleDriveBackupTest extends TestCase
{
    private string $temp;
    protected function setUp(): void
    {
        parent::setUp(); $this->temp = sys_get_temp_dir().'/google-test-'.bin2hex(random_bytes(6)); mkdir($this->temp, 0700);
        config(['backup.google.settings_path'=>$this->temp.'/settings.json', 'backup.google.client_id'=>'test-client', 'backup.google.client_secret'=>'test-secret']);
        Http::preventStrayRequests();
    }
    protected function tearDown(): void { foreach (glob($this->temp.'/*') as $path) unlink($path); rmdir($this->temp); parent::tearDown(); }
    private function fakeGoogle(?string $backup=null, bool $reuse=false, bool $wrongChecksum=false): void
    {
        Http::fake(function ($request) use ($backup,$reuse,$wrongChecksum) {
            if (str_contains($request->url(),'oauth2.googleapis.com')) return Http::response($request['grant_type']==='authorization_code' ? ['refresh_token'=>'fake-refresh','access_token'=>'fake-access'] : ['access_token'=>'fake-access']);
            if ($request->method()==='GET' && str_contains($request->url(),'files?')) return Http::response(['files'=>$reuse ? [['id'=>'managed-folder']] : []]);
            if ($request->method()==='POST' && !str_contains($request->url(),'/upload/')) return Http::response(['id'=>'managed-folder']);
            if ($request->method()==='GET') return Http::response(['mimeType'=>'application/vnd.google-apps.folder','trashed'=>false,'capabilities'=>['canAddChildren'=>true]]);
            if ($request->method()==='POST') return Http::response([],200,['Location'=>'https://www.googleapis.com/upload/drive/v3/files?upload_id=fake']);
            return Http::response(['id'=>'remote-file','size'=>(string)filesize($backup),'md5Checksum'=>$wrongChecksum ? str_repeat('0',32) : hash_file('md5',$backup)]);
        });
    }
    public function test_non_owners_cannot_manage_google_connection(): void
    {
        foreach ([User::CASHIER,User::MANAGER] as $role) {
            $this->actingAs(new User(['access_level'=>$role,'shop_id'=>1]));
            foreach (['google-connect','google-disconnect','google-setup'] as $action) $this->post('/backup/'.$action)->assertRedirect(route('pos'));
            $this->get('/backup/google-callback')->assertRedirect(route('pos'));
        }
        Http::assertNothingSent();
    }
    public function test_owner_can_save_encrypted_setup_and_invalid_secret_is_not_flashed(): void
    {
        $this->actingAs(new User(['access_level'=>User::OWNER]));
        $this->post('/backup/google-setup', ['google_client_id'=>'invalid','google_client_secret'=>'test-private-secret'])
            ->assertSessionHasErrors('google_client_id')->assertSessionMissing('_old_input.google_client_secret');
        $this->post('/backup/google-setup', ['google_client_id'=>'test-client.apps.googleusercontent.com','google_client_secret'=>'test-private-secret'])->assertRedirect(route('backup'));
        $stored=json_decode(file_get_contents($this->temp.'/settings.json'),true);
        $this->assertSame('test-private-secret',Crypt::decryptString($stored['client_secret']));
        $this->assertNotSame('test-private-secret',$stored['client_secret']);
        $google=new GoogleDriveBackup;
        parse_str(parse_url($google->authorizationUrl('test-state'),PHP_URL_QUERY),$query);
        $this->assertSame('test-client.apps.googleusercontent.com',$query['client_id']);
        Http::assertNothingSent();
    }
    public function test_connect_creates_private_managed_folder_and_stores_token_encrypted(): void
    {
        $this->fakeGoogle(); $google=new GoogleDriveBackup; $google->connect('fake-code');
        $stored=json_decode(file_get_contents($this->temp.'/settings.json'),true);
        $this->assertSame('fake-refresh',Crypt::decryptString($stored['refresh_token']));
        $this->assertNotSame('fake-refresh',$stored['refresh_token']);
        $this->assertSame(0600,fileperms($this->temp.'/settings.json') & 0777);
        $this->assertSame('managed-folder',$google->settings()['folder_id']);
        Http::assertSent(fn($r)=>$r->method()==='POST' && str_contains($r->url(),'drive/v3/files') && $r['name']==='DEMO RETAIL Backups' && $r['mimeType']==='application/vnd.google-apps.folder' && $r['parents']===['root']);
        Http::assertNotSent(fn($r)=>str_contains($r->url(),'/permissions'));
        parse_str(parse_url($google->authorizationUrl('test-state'),PHP_URL_QUERY),$query);
        $this->assertSame('https://www.googleapis.com/auth/drive.file',$query['scope']);
    }
    public function test_reconnect_reuses_app_created_folder_without_duplicates(): void
    {
        $this->fakeGoogle(null,true); $google=new GoogleDriveBackup; $google->connect('fake-code'); $google->connect('second-code');
        $this->assertSame('managed-folder',$google->settings()['folder_id']);
        Http::assertNotSent(fn($r)=>$r->method()==='POST' && str_contains($r->url(),'drive/v3/files'));
    }
    public function test_upload_uses_automatic_folder_and_checks_remote_checksum(): void
    {
        $backup=$this->temp.'/backup-test.bbbackup'; file_put_contents($backup,'synthetic-encrypted-backup');
        $this->fakeGoogle($backup); $google=new GoogleDriveBackup; $google->connect('fake-code'); $google->upload($backup);
        $this->assertNotNull($google->settings()['last_upload']);
        Http::assertSent(fn($r)=>$r->method()==='POST' && str_contains($r->url(),'/upload/drive') && $r['parents']===['managed-folder']);
    }
    public function test_bad_upload_checksum_does_not_report_success(): void
    {
        $backup=$this->temp.'/backup-test.bbbackup'; file_put_contents($backup,'synthetic-encrypted-backup');
        $this->fakeGoogle($backup,false,true); $google=new GoogleDriveBackup; $google->connect('fake-code');
        try { $google->upload($backup); $this->fail('Invalid upload accepted'); }
        catch(\RuntimeException $e) { $this->assertStringContainsString('checksum',$e->getMessage()); }
        $this->assertNull($google->settings()['last_upload']); $this->assertFileExists($backup);
    }
    public function test_missing_connection_and_wrong_oauth_state_do_not_upload(): void
    {
        $google=new GoogleDriveBackup;
        try { $google->upload($this->temp.'/anything'); $this->fail('Missing connection accepted'); }
        catch(\RuntimeException $e) { $this->assertStringContainsString('Connect Google Drive',$e->getMessage()); }
        $this->actingAs(new User(['access_level'=>User::OWNER]));
        $this->withSession(['backup_google_state'=>'expected'])->get('/backup/google-callback?state=wrong&code=test')->assertForbidden();
        Http::assertNothingSent();
    }
}
