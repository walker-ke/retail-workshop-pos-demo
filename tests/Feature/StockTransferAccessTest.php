<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class StockTransferAccessTest extends TestCase
{
    public function test_guests_cannot_read_or_change_stock_requests(): void
    {
        $this->get('/stock-requests')->assertRedirect(route('login'));
        $this->getJson('/stock-requests/alerts')->assertUnauthorized();
        $this->postJson('/stock-requests',[])->assertUnauthorized();
        $this->postJson('/stock-requests/1', ['action'=>'dispatch'])->assertUnauthorized();
    }
    public function test_staff_roles_can_use_requests_without_gaining_stock_adjustment_rights(): void
    {
        foreach ([User::CASHIER,User::MANAGER,User::OWNER] as $role) {
            $user=new User(['access_level'=>$role]);
            $this->assertTrue($user->hasAccess('stock_transfers'));
        }
        $this->assertFalse((new User(['access_level'=>User::CASHIER]))->hasAccess('stock'));
        $this->assertFalse((new User(['access_level'=>5]))->hasAccess('stock_transfers'));
    }
}
