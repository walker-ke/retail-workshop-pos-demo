<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Carbon\CarbonImmutable;
use App\Support\DailyPayments;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function profitMargin(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d', 'q' => 'nullable|string|max:100', 'period' => 'nullable|in:daily,weekly,monthly']);
        $date = $request->input('date') ?: now('Africa/Nairobi')->format('Y-m-d');
        $day = CarbonImmutable::parse($date, 'Africa/Nairobi')->startOfDay();
        $period = $request->input('period', 'daily');
        $rangeStart = match ($period) {
            'weekly' => $day->startOfWeek(\Carbon\CarbonInterface::MONDAY),
            'monthly' => $day->startOfMonth(),
            default => $day,
        };
        $rangeEnd = match ($period) {
            'weekly' => $rangeStart->addWeek(),
            'monthly' => $rangeStart->addMonth(),
            default => $rangeStart->addDay(),
        };
        $periodTitle = ucfirst($period);
        $periodLabel = $period === 'daily' ? $rangeStart->format('l, d F Y')
            : $rangeStart->format('d M Y').' – '.$rangeEnd->subDay()->format('d M Y');
        $start = $rangeStart->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $end = $rangeEnd->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $shopId = auth()->user()->activeShopId();

        $query = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->select(
                'products.id as product_id',
                'products.product_name',
                'products.product_code',
                'products.brand',
                DB::raw('SUM(order_details.quantity) as qty_sold'),
                DB::raw('SUM(order_details.total) as revenue'),
                DB::raw('SUM(order_details.quantity * products.cost_price) as cost'),
                DB::raw('SUM(order_details.total - (order_details.quantity * products.cost_price)) as profit')
            )
            ->groupBy('products.id', 'products.product_name', 'products.product_code', 'products.brand');

        if ($shopId) {
            $query->where('orders.shop_id', $shopId);
        }

        $query->where('orders.created_at', '>=', $start)->where('orders.created_at', '<', $end);
        $rows = $query->orderByDesc('profit')->get();

        $sales = DB::table('orders')->where('created_at', '>=', $start)->where('created_at', '<', $end);
        if ($shopId) $sales->where('shop_id', $shopId);
        $summary = DailyPayments::summary((clone $sales)->select('id', 'total', 'paid_amount', 'payment_method', 'payment_breakdown', 'created_at', 'order_status')->lazyById(250));
        $paymentTotals = [];
        foreach (\App\Models\PaymentType::where('enabled', true)->orderBy('position')->orderBy('name')->pluck('name') as $method) {
            $paymentTotals[$method] = 0;
        }
        // Retain historical methods even when they are no longer enabled.
        foreach ($summary['totals'] as $method => $amount) $paymentTotals[$method] = $amount;
        $hourly = $summary['hourly'];
        $transactionCount = $summary['count'];
        $pendingCount = $summary['pending'];
        $totalCollected = array_sum($paymentTotals);
        $search = trim($request->input('q', ''));
        if ($search !== '') {
            $sales->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', '%'.$search.'%')->orWhere('transaction_code', 'like', '%'.$search.'%');
            });
        }
        $transactions = $sales->select('id', 'transaction_code', 'customer_name', 'created_at', 'payment_method', 'payment_breakdown', 'paid_amount', 'total', 'order_status')
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(10)->withQueryString();

        $topProducts = $rows->sortBy([['qty_sold', 'desc'], ['product_name', 'asc']])->take(5)->values();
        $totalRevenue = $rows->sum('revenue');
        $totalCost = $rows->sum('cost');
        $totalProfit = $rows->sum('profit');

        return view('reports.profit_margin', compact('rows', 'totalRevenue', 'totalCost', 'totalProfit', 'shopId', 'date', 'paymentTotals', 'totalCollected', 'hourly', 'transactionCount', 'pendingCount', 'transactions', 'search', 'topProducts', 'period', 'periodTitle', 'periodLabel'));
    }
}
