<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class WorkshopAccessTest extends TestCase {
 public function test_guests_cannot_open_workshop(): void {$this->get('/workshop')->assertRedirect('/login');}
 public function test_cashiers_cannot_access_workshop_or_write_financial_records(): void {
  foreach([User::CASHIER] as $role){$this->actingAs(new User(['id'=>123,'access_level'=>$role,'active'=>true,'shop_id'=>1]));$this->get('/workshop/availability?hub_id=1&product_id=1')->assertForbidden();foreach(['dashboard','fundis','ledgers','stock'] as $view)$this->get('/workshop?view='.$view)->assertForbidden();foreach(['/workshop/fundis','/workshop/fundis/1','/workshop/production','/workshop/payments','/workshop/components'] as $path)$this->post($path,[])->assertForbidden();}
 }
 public function test_managers_reach_workshop_and_hub_form_validation(): void {
  $this->actingAs(new User(['id'=>123,'access_level'=>User::MANAGER,'active'=>true,'shop_id'=>1]));
  foreach(['/workshop/fundis','/workshop/production','/workshop/payments','/workshop/components','/hubs','/hubs/1/transfer','/hub-transfers/1/complete'] as $path)$this->postJson($path,[])->assertUnprocessable();
 }
 public function test_posted_ledger_records_have_no_edit_or_delete_routes(): void {$this->actingAs(new User(['id'=>123,'access_level'=>User::OWNER,'active'=>true]));$this->delete('/workshop/payments/1')->assertNotFound();$this->patch('/workshop/production/1',[])->assertNotFound();}
}
