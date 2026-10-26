<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;

class TransactionController extends Controller
{
    public function __construct() { $this->middleware('auth'); }

    public function index()
    {
        $shopId = auth()->user()->activeShopId();

        $query = DB::table('orders')
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->select('orders.*', 'shops.name as shop_name');

        if ($shopId) {
            $query->where('orders.shop_id', $shopId);
        }

        $perPage = \App\Support\HistoryFilters::apply($query, 'orders', ['transaction_code','customer_name','cashier_name','order_date','payment_method','order_status']);
        $orders = $query->orderByDesc('orders.id')->paginate($perPage)->withQueryString();
        return view('transaction.transaction_history',compact('orders'));
    }

    public function viewReceipt($id)
    {
        // No join to customers: a Walk-in sale has no customer_id, and
        // orders.customer_name already carries the name to display either way.
        $orders = DB::table('orders')
                    ->where('id', $id)
                    ->first();

        abort_unless($orders, 404);
        $shopId = auth()->user()->activeShopId();
        abort_if($shopId && (int) $orders->shop_id !== (int) $shopId, 403);

        $order_details = DB::table('order_details')
                    ->leftJoin('products','order_details.product_id','products.id')
                    ->select('order_details.*', DB::raw('COALESCE(order_details.product_name, products.product_name) as product_name'), DB::raw('COALESCE(order_details.product_code, products.product_code) as product_code'))
                    ->where('order_id', $id)
                    ->get();

        $shop = \App\Models\Shop::find($orders->shop_id);
        $receipt = \App\Support\ReceiptData::order($orders, $order_details, $shop);
        return view('transaction.view_receipt', compact('orders', 'order_details', 'receipt'));
    }
}
