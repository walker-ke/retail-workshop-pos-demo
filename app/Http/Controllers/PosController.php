<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Products;
use App\Models\Brands;
use App\Models\PaymentType;
use DB;

class PosController extends Controller
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

    public function index()
    {
        $shopId = auth()->user()->activeShopId();

        if (!$shopId) {
            return view('pos.select_shop');
        }

        $products = DB::table('products')
                    ->select('id', 'product_name', 'product_code', 'brand', 'price')
                    ->orderBy('product_name')
                    ->get();

        $stockQuery = DB::table('product_stock')
            ->select('product_id', DB::raw('SUM(quantity - reserved_quantity) as quantity'))
            ->groupBy('product_id');
        if ($shopId) {
            $stockQuery->where('shop_id', $shopId);
        }
        $stockByProduct = $stockQuery->pluck('quantity', 'product_id');
        $products->each(function ($p) use ($stockByProduct) {
            $p->quantity = (int) ($stockByProduct[$p->id] ?? 0);
        });

        $catalog = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->product_name,
                'code' => $product->product_code,
                'brand' => $product->brand,
                'price' => $product->price,
                'quantity' => $product->quantity,
            ];
        })->values();

        $categories = DB::table('products')
                    ->select('brand')
                    ->groupBy('brand')
                    ->orderByRaw('count(*) desc')
                    ->limit(14)
                    ->pluck('brand');
        $paymentTypes = PaymentType::where('enabled', true)->orderBy('position')->orderBy('name')->get();
        return view('pos.pos_index', compact('catalog','categories','shopId','paymentTypes'));
    }
}
