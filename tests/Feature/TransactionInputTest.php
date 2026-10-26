<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class TransactionInputTest extends TestCase
{
    public function test_invalid_cart_quantities_and_prices_are_rejected_before_cart_changes(): void
    {
        $this->actingAs(new User(['access_level'=>User::OWNER]));
        $this->postJson('/add-cart', ['name'=>'Test', 'quantity'=>-1, 'price'=>-5])->assertUnprocessable()->assertJsonValidationErrors(['id','quantity','price']);
        $this->postJson('/update-cart/missing', ['qty'=>0,'price'=>-1])->assertUnprocessable()->assertJsonValidationErrors(['qty','price']);
    }
    public function test_invalid_refund_input_is_rejected_before_database_changes(): void
    {
        $this->actingAs(new User(['access_level'=>User::OWNER]));
        $this->postJson('/refund-order/1', ['refund_qty'=>[1=>-1]])->assertUnprocessable()->assertJsonValidationErrors('refund_qty.1');
        $this->postJson('/refund-order/1', ['refund_qty'=>[1=>0]])->assertUnprocessable()->assertJsonValidationErrors('refund_qty');
        $this->postJson('/refund-order/1', ['refund_qty'=>[1=>1.5]])->assertUnprocessable()->assertJsonValidationErrors('refund_qty.1');
    }
}
