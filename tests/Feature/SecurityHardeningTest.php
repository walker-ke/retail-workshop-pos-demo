<?php
namespace Tests\Feature;
use App\Models\User;
use App\Support\CsvCell;
use Tests\TestCase;
class SecurityHardeningTest extends TestCase
{
    public function test_security_headers(): void
    {
        $this->get('/')->assertOk()->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('Cache-Control','no-store, private')->assertHeader('Content-Security-Policy',"base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'");
    }
    public function test_guest_cannot_access_business_records(): void
    {
        foreach (['/pos','/home','/view-receipt/1','/backup/recovery-key','/stock','/refund-order/1'] as $url) $this->get($url)->assertRedirect(route('login'));
        foreach (['/add-user','/add-cart','/add-transaction','/refund-order/1','/backup/restore'] as $url) $this->postJson($url)->assertUnauthorized();
    }
    public function test_staff_cannot_access_backups(): void
    {
        foreach ([User::CASHIER,User::MANAGER] as $role) {
            $this->actingAs(new User(['access_level'=>$role,'shop_id'=>1,'active'=>true]));
            $this->get('/backup/recovery-key')->assertRedirect(route('pos'));
            $this->post('/backup/restore',['confirmation'=>'RESTORE'])->assertRedirect(route('pos'));
        }
    }
    public function test_google_state_rejects_missing_wrong_and_replayed_values(): void
    {
        $this->actingAs(new User(['access_level'=>9,'active'=>true]));
        $this->get('/backup/google-callback?code=forged')->assertForbidden();
        $this->withSession(['backup_google_state'=>'expected']);
        $this->get('/backup/google-callback?code=forged&state=wrong')->assertForbidden();
        $this->get('/backup/google-callback?code=forged&state=expected')->assertForbidden();
    }
    public function test_login_rejects_arrays(): void
    {
        $this->postJson('/login',['login'=>['admin'],'password'=>['guess']])->assertUnprocessable()->assertJsonValidationErrors(['login','password']);
    }
    public function test_public_password_recovery_is_disabled(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Forgot Your Password?');
        $this->get('/password/reset')->assertNotFound();
        $this->get('/password/reset/example-token')->assertNotFound();
        $this->postJson('/password/email', [])->assertNotFound();
        $this->postJson('/password/reset', [])->assertNotFound();
    }
    public function test_short_admin_reset_password_is_rejected(): void
    {
        $this->actingAs(new User(['access_level'=>9,'active'=>true]));
        $this->postJson('/reset-password/1',['password'=>'short','password_confirmation'=>'short'])->assertRedirect(route('user'))->assertSessionHas('error');
    }
    public function test_state_changes_require_csrf_token(): void
    {
        $middleware = new class($this->app, $this->app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {
            protected function runningUnitTests() { return false; }
        };
        $request = \Illuminate\Http\Request::create('/add-transaction', 'POST');
        $request->setLaravelSession($this->app['session']->driver());
        $this->expectException(\Illuminate\Session\TokenMismatchException::class);
        $middleware->handle($request, fn () => response('unsafe'));
    }
    public function test_record_ids_cannot_contain_validation_rule_syntax(): void
    {
        $this->actingAs(new User(['access_level'=>9,'active'=>true]));
        $this->post('/update-user/1,username,NULL')->assertNotFound();
        $this->post('/update-product/1,product_code,NULL')->assertNotFound();
    }
    public function test_csv_formula_injection_is_neutralized(): void
    {
        foreach (['=1+1','+SUM(1)','-cmd','@SUM(1)',"\t=1+1","\r+cmd"] as $value) $this->assertSame("'".$value,CsvCell::safe($value));
        $this->assertSame('Shirt',CsvCell::safe('Shirt'));
        $this->assertSame(12.5,CsvCell::safe(12.5));
    }
}
