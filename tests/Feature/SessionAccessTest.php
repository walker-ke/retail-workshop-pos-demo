<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class SessionAccessTest extends TestCase
{
    public function test_disabled_account_cannot_keep_using_an_existing_session(): void
    {
        $this->actingAs(new User(['name'=>'Disabled cashier','access_level'=>0,'shop_id'=>1,'active'=>false]));
        $this->withSession(['current_shop_id'=>1]);
        $this->postJson('/update-cart/stale', ['qty'=>1])->assertUnauthorized();
        $this->assertGuest();
        $this->assertNull(session('current_shop_id'));
    }
    public function test_password_reset_invalidates_an_existing_session(): void
    {
        $this->actingAs(new User(['name'=>'Cashier','access_level'=>0,'shop_id'=>1,'active'=>true,'password'=>'new-password-hash']));
        $this->withSession(['password_hash_web'=>'old-password-hash']);
        $this->postJson('/update-cart/stale', ['qty'=>1])->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_current_password_session_remains_usable(): void
    {
        $this->actingAs(new User(['name'=>'Cashier','access_level'=>0,'shop_id'=>1,'active'=>true,'password'=>'current-password-hash']));
        $this->withSession(['password_hash_web'=>'current-password-hash']);
        $this->postJson('/update-cart/stale', ['qty'=>1])->assertOk();
        $this->assertAuthenticated();
    }

    public function test_staff_cannot_inherit_an_owner_shop_selection(): void
    {
        $this->withSession(['current_shop_id'=>20]);
        $this->assertSame(1,(new User(['access_level'=>0,'shop_id'=>1]))->activeShopId());
        $this->assertNull((new User(['access_level'=>1,'shop_id'=>null]))->activeShopId());
        $this->assertSame(20,(new User(['access_level'=>9,'shop_id'=>1]))->activeShopId());
        session(['current_shop_id'=>null]);
        $this->assertNull((new User(['access_level'=>9,'shop_id'=>1]))->activeShopId());
    }
    public function test_unassigned_or_unknown_roles_cannot_use_pos_endpoints(): void
    {
        foreach ([['access_level'=>0,'shop_id'=>null],['access_level'=>5,'shop_id'=>1]] as $data) {
            $this->actingAs(new User($data+['name'=>'Unassigned','active'=>true]));
            $this->postJson('/update-cart/stale',['qty'=>1])->assertForbidden();
            $this->get('/transaction-history')->assertForbidden();
        }
    }
}
