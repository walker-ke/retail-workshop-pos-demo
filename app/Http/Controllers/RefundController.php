<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use RealRashid\SweetAlert\Facades\Alert;

class RefundController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $shopId = auth()->user()->activeShopId();

        $ordersQuery = DB::table('orders')
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->select('orders.*', 'shops.name as shop_name')
            ->orderByDesc('orders.id');
        $refundsQuery = DB::table('refunds')
            ->join('shops', 'refunds.shop_id', '=', 'shops.id')
            ->select('refunds.*', 'shops.name as shop_name')
            ->orderByDesc('refunds.id');

        if ($shopId) {
            $ordersQuery->where('orders.shop_id', $shopId);
            $refundsQuery->where('refunds.shop_id', $shopId);
        }

        $perPage = \App\Support\HistoryFilters::apply($ordersQuery, 'orders', ['transaction_code','customer_name','cashier_name','order_date','payment_method','order_status']);
        \App\Support\HistoryFilters::apply($refundsQuery, 'refunds', ['transaction_code','customer_name','reason']);
        $orders = $ordersQuery->paginate($perPage, ['*'], 'orders_page')->withQueryString();
        $refunds = $refundsQuery->paginate($perPage, ['*'], 'refunds_page')->withQueryString();
        return view('refunds.refund_index', compact('orders', 'refunds'));
    }

    public function showOrder($id)
    {
        $order = DB::table('orders')->where('id', $id)->first();
        $this->authorizeOrder($order);

        $orderDetails = DB::table('order_details')
            ->leftJoin('products', 'order_details.product_id', '=', 'products.id')
            ->select('order_details.*', DB::raw('COALESCE(order_details.product_name, products.product_name) as product_name'), DB::raw('COALESCE(order_details.product_code, products.product_code) as product_code'))
            ->where('order_id', $id)
            ->get()->groupBy('product_id')->map(fn ($rows) => (object) [
                'product_id' => $rows->first()->product_id, 'product_name' => $rows->first()->product_name, 'product_code' => $rows->first()->product_code,
                'quantity' => $rows->sum('quantity'), 'unit_price' => $rows->sum(fn ($row) => \App\Support\ReceiptData::amount($row->total)) / max(1, $rows->sum('quantity')),
            ])->values();

        $alreadyRefunded = DB::table('refund_details')
            ->join('refunds', 'refund_details.refund_id', '=', 'refunds.id')
            ->where('refunds.order_id', $id)
            ->select('refund_details.product_id', DB::raw('SUM(refund_details.quantity) as refunded_qty'))
            ->groupBy('refund_details.product_id')
            ->pluck('refunded_qty', 'product_id');

        return view('refunds.refund_order', compact('order', 'orderDetails', 'alreadyRefunded'));
    }

    private function authorizeOrder($order): void
    {
        abort_unless($order, 404);
        $user = auth()->user();
        abort_if(!$user->isTopAdmin() && (!$user->activeShopId() || (int) $order->shop_id !== (int) $user->activeShopId()), 403);
    }

    public function store(Request $request, $id)
    {
        $request->validate([
            'refund_qty' => 'required|array',
            'refund_qty.*' => 'nullable|integer|min:0',
            'reason' => 'nullable|string|max:1000',
        ]);
        $quantities = array_filter($request->input('refund_qty'), fn ($qty) => (int) $qty > 0);
        if (!$quantities) throw \Illuminate\Validation\ValidationException::withMessages(['refund_qty' => 'Select at least one item to refund.']);

        \App\Support\StockTransactions::run(function () use ($request, $id, $quantities) {
            // Every refund of this receipt takes the same order lock before checking remaining quantities.
            $order = DB::table('orders')->where('id', $id)->lockForUpdate()->first();
            $this->authorizeOrder($order);
            $details = DB::table('order_details')->where('order_id', $id)->get()->groupBy('product_id');
            $prior = DB::table('refund_details')->join('refunds', 'refund_details.refund_id', '=', 'refunds.id')
                ->where('refunds.order_id', $id)->select('refund_details.*')->get()->groupBy('product_id');
            $lines = []; $fullyRefunded = true;
            foreach ($quantities as $productId => $qty) {
                if (!ctype_digit((string) $productId) || !isset($details[$productId])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['refund_qty' => 'Choose a product from this receipt.']);
                }
                $sold = $details[$productId];
                $previous = $prior->get($productId, collect());
                $remaining = $sold->sum('quantity') - $previous->sum('quantity');
                if ((int) $qty > $remaining) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['refund_qty.'.$productId => 'Refund quantity exceeds the quantity remaining on this receipt.']);
                }
                $soldAmount = $sold->sum(fn ($line) => \App\Support\ReceiptData::amount($line->total));
                $unitPrice = $soldAmount / $sold->sum('quantity');
                $amount = (int) $qty === (int) $remaining
                    ? $soldAmount - $previous->sum(fn ($line) => \App\Support\ReceiptData::amount($line->total))
                    : round($unitPrice * (int) $qty, 2);
                $lines[] = ['product_id' => (int) $productId, 'quantity' => (int) $qty, 'unit_price' => $unitPrice, 'total' => round($amount, 2)];
            }
            foreach ($details as $productId => $sold) {
                if ($sold->sum('quantity') > $prior->get($productId, collect())->sum('quantity') + (int) ($quantities[$productId] ?? 0)) $fullyRefunded = false;
            }
            \App\Support\StockTransactions::lock(array_column($lines, 'product_id'), (int) $order->shop_id);
            $refundId = DB::table('refunds')->insertGetId([
                'shop_id' => $order->shop_id, 'order_id' => $id, 'transaction_code' => $order->transaction_code,
                'customer_name' => $order->customer_name, 'reason' => $request->input('reason'),
                'total_refunded' => round(array_sum(array_column($lines, 'total')), 2), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $line) {
                DB::table('refund_details')->insert(array_merge($line, ['refund_id' => $refundId, 'created_at' => now(), 'updated_at' => now()]));
                DB::table('product_stock')->where('product_id', $line['product_id'])->where('shop_id', $order->shop_id)->increment('quantity', $line['quantity']);
            }
            DB::table('orders')->where('id', $id)->update(['order_status' => $fullyRefunded ? 'Refunded' : 'Partially Refunded']);
        });
        Alert::success('Refund Processed', '');
        return redirect()->route('refunds')->with('success', 'Refund Processed Successfully');
    }
}
