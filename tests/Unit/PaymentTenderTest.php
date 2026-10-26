<?php
namespace Tests\Unit;
use App\Support\CombinedPayment;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class PaymentTenderTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider("invalidAmounts")]
    public function test_invalid_paid_amounts_are_rejected($paid, bool $changeAllowed, bool $markPaid): void
    {
        $this->expectException(ValidationException::class);
        CombinedPayment::tender('100.00', $paid, $changeAllowed, $markPaid);
    }
    public static function invalidAmounts(): array
    {
        return [[0,false,true],[99.99,false,true],[100.01,false,true],[99.99,true,true],['100.001',true,true],['1e2',true,true],[-1,true,true],['',true,true],[101,false,false]];
    }
    public function test_valid_cash_exact_electronic_and_pending_amounts_are_preserved(): void
    {
        $this->assertSame(120.0,CombinedPayment::tender('100.00','120.00',true,true));
        $this->assertSame(100.0,CombinedPayment::tender('100.00','100.00',false,true));
        $this->assertSame(50.0,CombinedPayment::tender('100.00','50.00',false,false));
        $this->assertSame(1000.1,CombinedPayment::tender('1,000.10','1000.10',false,true));
    }
}
