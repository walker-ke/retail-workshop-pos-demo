<?php
namespace Tests\Feature;

use App\Support\DailyPayments;
use App\Models\User;
use Tests\TestCase;

class DailyPaymentsTest extends TestCase
{
    public function test_collections_exclude_change_split_payments_and_unpaid_credit(): void
    {
        $order = fn ($total, $paid, $method, $parts = null) => (object) ['total'=>$total, 'paid_amount'=>$paid, 'payment_method'=>$method, 'payment_breakdown'=>$parts];
        $totals = DailyPayments::totals([
            $order('1,000.00', '1,500.00', 'Cash'),
            $order(100, 100, 'Combined payment', json_encode([['method'=>'Cash','amount'=>25], ['method'=>'Paybill','amount'=>75]])),
            $order(500, 0, 'Credit'),
            $order(500, 100, 'Credit'),
            $order(5.25, 5.25, 'Old bank method'),
            $order(20, 20, 'Combined payment', '{bad json'),
        ]);
        $this->assertSame(102500, $totals['Cash']);
        $this->assertSame(7500, $totals['Paybill']);
        $this->assertSame(10000, $totals['Credit']);
        $this->assertSame(525, $totals['Old bank method']);
        $this->assertSame(2000, $totals['Combined payment']);
        $this->assertSame(122525, array_sum($totals));
    }

    public function test_hourly_summary_uses_nairobi_time_and_keeps_full_day_totals(): void
    {
        config(['app.timezone'=>'UTC']);
        $order = fn ($time, $paid, $status) => (object) ['total'=>100, 'paid_amount'=>$paid, 'payment_method'=>'Cash', 'payment_breakdown'=>null, 'created_at'=>$time, 'order_status'=>$status];
        $summary = DailyPayments::summary([
            $order('2026-10-24 21:00:00', 100, 'Paid'),
            $order('2026-10-25 20:59:59', 30, 'Pending'),
        ]);
        $this->assertSame(24, count($summary['hourly']));
        $this->assertSame(10000, $summary['hourly'][0]['Cash']);
        $this->assertSame(3000, $summary['hourly'][23]['Cash']);
        $this->assertSame(13000, $summary['totals']['Cash']);
        $this->assertSame(2, $summary['count']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(['totals'=>[], 'hourly'=>array_fill(0,24,[]), 'count'=>0, 'pending'=>0], DailyPayments::summary([]));
    }

    public function test_invalid_report_date_is_rejected_before_sales_queries(): void
    {
        $this->actingAs(new User(['access_level'=>User::OWNER]));
        $this->get('/reports/profit-margin?date=2026-02-30')->assertRedirect()->assertSessionHasErrors('date');
    }
}
