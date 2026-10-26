<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class HubAccessTest extends TestCase
{
    public function test_guests_cannot_access_hubs(): void {$this->get('/hubs')->assertRedirect('/login');}
    public function test_cashiers_cannot_manage_hubs(): void {
        foreach([User::CASHIER] as $role){
            $this->actingAs(new User(['id'=>42,'access_level'=>$role,'shop_id'=>1,'active'=>true]));
            foreach(['/hubs','/hubs/stock','/hubs/send','/hubs/1/stock','/hubs/1/send','/hubs/1/products/1/stock','/list-suppliers'] as $path)$this->get($path)->assertForbidden();
            foreach(['/hubs','/hubs/1/stock','/hubs/1/send','/hubs/1/remove','/hubs/1/rename','/hub-deliveries/1/resolve'] as $path)$this->post($path,[])->assertForbidden();
        }
    }
    public function test_supplier_writes_are_no_longer_exposed(): void {
        $this->actingAs(new User(['id'=>42,'access_level'=>User::OWNER,'active'=>true]));
        $this->post('/add-supplier',[])->assertNotFound();$this->get('/add-supplier')->assertNotFound();
    }
}
