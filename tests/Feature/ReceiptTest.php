<?php
namespace Tests\Feature;

use App\Models\User;
use App\Support\ReceiptData;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    public function test_cashiers_cannot_view_or_change_receipt_settings(): void
    {
        $user = new class(['name' => 'Sharon']) extends User {
            public function isTopAdmin(): bool { return false; }
        };
        $this->actingAs($user);
        $this->get('/receipt-settings')->assertForbidden();
        $this->post('/receipt-settings/1', ['receipt_name' => 'Forged', 'receipt_paybill' => '999'])->assertForbidden();
    }

    public function test_saved_receipt_keeps_original_business_and_cashier_and_has_no_tax_rows(): void
    {
        $shop = (object) ['id' => 1, 'receipt_name' => 'Original shop', 'receipt_phone' => '0700000000', 'receipt_paybill' => '001234', 'receipt_account_number' => '000456'];
        config(['app.timezone' => 'UTC']);
        $settings = ReceiptData::business($shop);
        $order = (object) ['receipt_settings' => json_encode($settings), 'cashier_id' => null, 'cashier_name' => 'Sharon', 'total' => '1,000.00', 'paid_amount' => '1,200.00', 'payment_method' => 'Cash', 'customer_name' => 'Brian', 'created_at' => '2026-10-09 12:00:00', 'transaction_code' => '123456'];
        $shop->receipt_name = 'Renamed shop';
        $shop->receipt_paybill = 'Changed';
        $details = collect([(object) ['product_name' => '<Uniform>', 'product_code' => '0001', 'quantity' => 2, 'unit_price' => 500, 'total' => 1000]]);
        $receipt = ReceiptData::order($order, $details, $shop);
        $this->assertSame($settings, $receipt['business']);
        $this->assertSame('Sharon', $receipt['served_by']);
        $this->assertSame('09-Oct-2026 3:00 pm', $receipt['date']);
        $this->assertSame(200.0, $receipt['change']);
        $html = view('receipts.thermal', compact('receipt'))->render();
        foreach (['Original shop', '001234', '000456', 'Sharon', '&lt;Uniform&gt;', '1,000.00'] as $text) $this->assertStringContainsString($text, $html);
        $this->assertStringContainsString('Receipt No:', $html);
        $this->assertStringContainsString('123456', $html);
        $this->assertStringNotContainsString('TAX', $html);
        $this->assertStringNotContainsString('SUB-TOTAL', $html);
        $this->assertStringNotContainsString('Renamed shop', $html);
        $order->cashier_name = null;
        $order->receipt_settings = null;
        $this->assertSame('Not recorded (older sale)', ReceiptData::order($order, $details, $shop)['served_by']);
    }

    public function test_preview_uses_logged_in_cashier_and_cannot_be_mistaken_for_completed_sale(): void
    {
        $receipt = ReceiptData::preview((object) ['id' => 1], (object) ['customer_name' => 'Walk-in Customer'], collect([(object) ['name' => 'Uniform', 'options' => (object) ['code' => '001'], 'qty' => 1, 'price' => 200]]), 'Cash', (object) ['name' => 'Sharon'], '200.00');
        $this->assertSame('Sharon', $receipt['served_by']);
        $this->assertNull($receipt['number']);
        $this->assertStringContainsString('not a completed sale', view('receipts.thermal', compact('receipt'))->render());
    }
    public function test_optional_customer_name_defaults_to_walk_in_and_trims_named_customers(): void
    {
        $this->assertSame('Walk-in Customer', ReceiptData::customerName(null));
        $this->assertSame('Walk-in Customer', ReceiptData::customerName('   '));
        $this->assertSame('Brian', ReceiptData::customerName(' Brian '));
    }

}
