<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Orders;
use Carbon\Carbon;
use DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $request->validate([
            'week' => 'nullable|date_format:Y-m-d',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''),
        ]);
        $weekStart = Carbon::parse($request->query('week', now('Africa/Nairobi')->toDateString()), 'Africa/Nairobi')->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();
        $shopId = auth()->user()->activeShopId();

        $monthStart = $weekStart->copy()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $from = $request->query('from') ? Carbon::parse($request->query('from'), 'Africa/Nairobi')->startOfDay() : now('Africa/Nairobi')->startOfDay();
        $to = $request->query('to') ? Carbon::parse($request->query('to'), 'Africa/Nairobi')->endOfDay() : now('Africa/Nairobi')->endOfDay();
        $weeklySales = array_fill(0, 7, 0.0);
        $monthOrderCount = 0; $dailyTotals = []; $rangeDates = [];
        $rangeTotal = 0.0; $total = 0.0; $order_count = 0;

        // Transfer daily summaries, never the complete sales history, into PHP.
        // Keep parsing legacy date formats exactly as before.
        $daily = DB::table('orders')->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->select('order_date')->selectRaw('SUM(total) as amount, COUNT(*) as sale_count')
            ->groupBy('order_date')->cursor();
        foreach ($daily as $day) {
            $amount = (float) $day->amount;
            $total += $amount; $order_count += (int) $day->sale_count;
            $date = $this->parseOrderDate($day->order_date);
            if (!$date) continue;
            if ($date->between($weekStart, $weekEnd)) $weeklySales[$date->dayOfWeekIso - 1] += $amount;
            if ($date->between($monthStart, $monthEnd)) {
                $monthOrderCount += (int) $day->sale_count;
                $key = $date->toDateString();
                $dailyTotals[$key] = ($dailyTotals[$key] ?? 0) + $amount;
            }
            if ($date->between($from, $to)) {
                $rangeDates[] = $day->order_date;
                $rangeTotal += $amount;
            }
        }
        $maxWeekly = max($weeklySales);
        arsort($dailyTotals);
        $topDayAmount = reset($dailyTotals) ?: 0;
        $topDayName = $topDayAmount > 0 ? Carbon::parse(array_key_first($dailyTotals))->format('D, d M') : null;

        $rangeQuery = fn () => DB::table('orders')->whereIn('orders.order_date', $rangeDates)
            ->when($shopId, fn ($q) => $q->where('orders.shop_id', $shopId));
        $lines = fn () => $rangeQuery()->join('order_details', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'order_details.product_id', '=', 'products.id');
        $topProducts = $lines()->selectRaw('products.product_name as name, SUM(order_details.quantity) as qty, SUM(order_details.total) as total')
            ->groupBy('products.product_name')->orderByDesc('qty')->limit(5)->get();
        $topGroups = $lines()->selectRaw("COALESCE(NULLIF(products.brand, ''), 'No group') as name, SUM(order_details.total) as total")
            ->groupBy('name')->orderByDesc('total')->limit(5)->get();

        $hourlySales = array_fill(0, 24, 0.0);
        $hours = $rangeQuery()->whereNotNull('orders.created_at')
            ->selectRaw("DATE_FORMAT(orders.created_at, '%Y-%m-%d %H:00:00') as sale_hour, SUM(orders.total) as amount")
            ->groupBy('sale_hour')->cursor();
        foreach ($hours as $hour) {
            $localHour = Carbon::parse($hour->sale_hour, config('app.timezone'))->timezone('Africa/Nairobi')->hour;
            $hourlySales[$localHour] += (float) $hour->amount;
        }
        $hasHourlyData = array_sum($hourlySales) > 0;
        $maxHourly = max($hourlySales) ?: 0;

        // ===== GLOBAL STAT CARDS =====
        // Product catalog and customer list are shared across shops; sales
        // totals respect the shop filter like the rest of this page.
        $product = DB::table('products')->count();
        $customer = DB::table('customers')->count();

        // ===== SALES BY SHOP (only meaningful in "all shops" admin view) =====
        $salesByShop = collect();
        if (!$shopId) {
            $salesByShop = DB::table('orders')
                ->join('shops', 'orders.shop_id', '=', 'shops.id')
                ->select('shops.name', DB::raw('SUM(orders.total) as total'), DB::raw('COUNT(orders.id) as order_count'))
                ->groupBy('shops.id', 'shops.name')
                ->orderByDesc('total')
                ->get();
        }

        return view('home', compact(
            'weekStart', 'weekEnd', 'weeklySales', 'maxWeekly', 'monthStart', 'monthOrderCount', 'topDayName', 'topDayAmount',
            'from', 'to', 'rangeTotal', 'topProducts', 'topGroups',
            'hourlySales', 'hasHourlyData', 'maxHourly',
            'product', 'total', 'customer', 'order_count', 'shopId', 'salesByShop'
        ));
    }

    private function parseOrderDate($value)
    {
        if (!$value) {
            return null;
        }
        foreach (['d/m/y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value, 'Africa/Nairobi')->startOfDay();
            } catch (\Exception $e) {
                continue;
            }
        }
        return null;
    }

}
