<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class ProductPriceAccessTest extends TestCase {
 public function test_owner_and_manager_reach_price_validation(): void {
  foreach([User::OWNER,User::MANAGER] as $role){$this->actingAs(new User(['id'=>123,'access_level'=>$role,'active'=>true,'shop_id'=>1]));$this->postJson('/products/1/price',[])->assertUnprocessable()->assertJsonValidationErrors(['price','expected_price']);}
 }
 public function test_cashier_cannot_change_prices(): void {
  $user=new User(['id'=>123,'access_level'=>User::CASHIER,'active'=>true,'shop_id'=>1]);$this->assertFalse($user->hasAccess('edit_prices'));$this->actingAs($user)->postJson('/products/1/price',['price'=>100,'expected_price'=>0])->assertRedirect('/pos');
 }
 public function test_manager_receives_only_focused_product_permissions(): void {
  $user=new User(['access_level'=>User::MANAGER,'active'=>true]);$this->assertTrue($user->hasAccess('edit_prices'));$this->assertTrue($user->hasAccess('view_products'));$this->assertFalse($user->hasAccess('products'));
 }
}
