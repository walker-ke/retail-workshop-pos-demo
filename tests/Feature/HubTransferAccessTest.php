<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class HubTransferAccessTest extends TestCase {
 public function test_guests_must_sign_in(): void {$this->get('/hubs/transfers')->assertRedirect('/login');}
 public function test_cashiers_and_inactive_users_cannot_access_hub_transfers(): void {
  foreach([[User::CASHIER,true],[User::MANAGER,false],[User::OWNER,false]] as [$role,$active]){
   $this->actingAs(new User(['id'=>123,'access_level'=>$role,'active'=>$active,'shop_id'=>1]));
   foreach([['GET','/hubs/transfers'],['POST','/hubs/1/transfer'],['POST','/hub-transfers/1/complete']] as [$method,$path]){$this->actingAs(new User(['id'=>123,'access_level'=>$role,'active'=>$active,'shop_id'=>1]));$response=$this->call($method,$path);if($active)$response->assertForbidden();else $response->assertRedirect('/login');}
  }
 }
 public function test_transfer_history_cannot_be_edited_or_deleted(): void {
  $this->actingAs(new User(['id'=>123,'access_level'=>User::OWNER,'active'=>true]));$this->delete('/hub-transfers/1')->assertNotFound();$this->patch('/hub-transfers/1',[])->assertNotFound();
 }
}
