<?php
namespace Tests\Feature;
use App\Support\CombinedPayment;
use App\Support\ReceiptData;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CombinedPaymentTest extends TestCase
{
    public function test_exact_cash_and_paybill_parts_are_saved_and_rendered_on_receipt(): void
    {
        $parts = CombinedPayment::breakdown('1,000.10', '500.05', '500.05');
        $this->assertSame([['method' => 'Cash', 'amount' => 500.05], ['method' => 'Paybill', 'amount' => 500.05]], $parts);
        $order = (object) ['payment_method' => 'Combined payment', 'payment_breakdown' => json_encode($parts), 'receipt_settings' => null, 'cashier_name' => 'Sharon', 'total' => '1000.10', 'paid_amount' => '1000.10', 'customer_name' => 'Brian', 'order_date' => '10/10/26', 'transaction_code' => '12345'];
        $receipt = ReceiptData::order($order, collect(), (object) ['id' => 1]);
        $this->assertSame(0, $receipt['change']);
        $html = view('receipts.thermal', compact('receipt'))->render();
        $this->assertStringContainsString('CASH:', $html);
        $this->assertStringContainsString('PAYBILL:', $html);
        $this->assertStringContainsString('500.05', $html);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("invalidSplits")]
    public function test_invalid_or_incomplete_combined_payment_is_rejected($cash, $paybill): void
    {
        $this->expectException(ValidationException::class);
        CombinedPayment::breakdown('1000.00', $cash, $paybill);
    }

    public static function invalidSplits(): array
    {
        return [['400', '500'], ['600', '500'], ['0', '1000'], ['1000', '0'], ['-100', '1100'], ['500.001', '499.999'], ['5e2', '500'], ['', '1000']];
    }
}
