<?php

namespace App\Http\Controllers;

use App\Models\StockIn;
use App\Models\Products;
use App\Models\Brands;
use App\Models\Shop;
use Illuminate\Http\Request;
use DB;

class StockInController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Per-product on-hand quantity for the given shop, or summed across all
     * shops when $shopId is null (the top admin's "all shops" view).
     */
    private function stockMap(?int $shopId)
    {
        $query = DB::table('product_stock')
            ->select('product_id', DB::raw('SUM(quantity) as quantity'))
            ->groupBy('product_id');

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return $query->pluck('quantity', 'product_id');
    }

    //STOCK OVERVIEW (inventory valuation, grouped/filtered like the Products page)
    public function overview(Request $request)
    {
        $selectedBrand = $request->query('brand');
        $shopId = auth()->user()->activeShopId();

        $query = Products::query();
        if ($selectedBrand) {
            $query->where('brand', $selectedBrand);
        }

        $prod = $query->orderBy('product_name')->get();

        $stockByProduct = $this->stockMap($shopId);
        $prod->each(function ($p) use ($stockByProduct) {
            $p->quantity = (int) ($stockByProduct[$p->id] ?? 0);
        });

        $negativeCount = $prod->filter(fn ($p) => $p->quantity < 0)->count();
        $zeroCount = $prod->filter(fn ($p) => $p->quantity == 0)->count();
        $nonZeroCount = $prod->filter(fn ($p) => $p->quantity != 0)->count();
        $activeCount = $prod->count();

        $brand = Brands::orderBy('brand_name')->get();
        $products = DB::table('products')->orderBy('product_name')->get();
        $products->each(function ($p) use ($stockByProduct) {
            $p->quantity = (int) ($stockByProduct[$p->id] ?? 0);
        });
        $shops = Shop::where('is_active', true)->orderBy('name')->get();

        return view('stock.stock_index', compact(
            'prod', 'brand', 'selectedBrand',
            'negativeCount', 'zeroCount', 'nonZeroCount', 'activeCount',
            'products', 'shops', 'shopId'
        ));
    }

    //PRINTABLE INVENTORY COUNT SHEET (system quantity + blank column to write the physical count)
    public function inventoryCountReport(Request $request)
    {
        $selectedBrand = $request->query('brand');
        $shopId = auth()->user()->isTopAdmin()
            ? ($request->query('shop_id') ?: auth()->user()->activeShopId())
            : auth()->user()->activeShopId();

        $query = Products::query();
        if ($selectedBrand) {
            $query->where('brand', $selectedBrand);
        }

        $prod = $query->orderBy('brand')->orderBy('product_name')->get();

        $stockByProduct = $this->stockMap($shopId ? (int) $shopId : null);
        $prod->each(function ($p) use ($stockByProduct) {
            $p->quantity = (int) ($stockByProduct[$p->id] ?? 0);
        });

        $shopName = $shopId ? Shop::find($shopId)?->name : 'All Shops (combined)';

        return view('stock.inventory_count_report', compact('prod', 'selectedBrand', 'shopName'));
    }

    public function store(Request $request)
    {
        $validateData = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'cost_price' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:65535',
            'request_key' => ($request->expectsJson()?'required':'nullable').'|uuid',
        ]);

        $shopId = auth()->user()->activeShopId();
        if (!$shopId && auth()->user()->isTopAdmin()) $shopId = $request->shop_id;

        if (!$shopId) {
            if($request->expectsJson())throw \Illuminate\Validation\ValidationException::withMessages(['shop_id'=>'Select which shop this stock is being added to.']);
            return redirect()->back()->with('error', 'Select which shop this stock is being added to.');
        }

        $product = Products::find($request->product_id);

        $key=$validateData['request_key']??null;
        $hash=hash('sha256',json_encode([auth()->id(),(int)$shopId,(int)$product->id,(int)$request->quantity,auth()->user()->isTopAdmin()?(string)($request->cost_price??'0'):'0',$request->reference??null,$request->notes??null]));
        try{\App\Support\StockTransactions::run(function () use ($request, $shopId, $product, $key, $hash) {
            \App\Support\StockTransactions::lock([$product->id], (int) $shopId);
            if($key){$prior=DB::table('stock_ins')->where('request_key',$key)->first();if($prior){abort_unless(hash_equals($prior->request_hash,$hash),409,'This stock receipt was already submitted with different details.');return;}}
            DB::table('stock_ins')->insert([
                'request_key'=>$key,'request_hash'=>$key?$hash:null,
                'shop_id' => $shopId,
                'product_id' => $request->product_id,
                'supplier_id' => null,
                'quantity' => $request->quantity,
                'cost_price' => auth()->user()->isTopAdmin() ? ($request->cost_price ?: 0) : 0,
                'reference' => $request->reference,
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('product_stock')
                ->where('product_id', $request->product_id)
                ->where('shop_id', $shopId)
                ->increment('quantity', (int) $request->quantity);

            if (auth()->user()->isTopAdmin() && $request->cost_price) {
                $product->cost_price = $request->cost_price;
                $product->save();
            }
        });

        }catch(\Illuminate\Database\QueryException $e){if((int)($e->errorInfo[1]??0)===1062)abort(409,'This stock receipt key was already used.');throw $e;}
        if($request->expectsJson())return $this->stockResponse($product->fresh(),'Stock updated for '.$product->product_name);
        return redirect()->back()->with('success', 'Stock Added Successfully to '.$product->product_name);
    }

    //QUICK "SET ABSOLUTE QUANTITY" UPDATE (right-click a Stock row). Logs the
    //resulting +/- delta as a stock_ins row so the count still has an audit trail.
    public function quickUpdate(Request $request)
    {
        $shopId = auth()->user()->activeShopId();

        if (!$shopId) {
            return response()->json(['message' => 'Select a shop before adjusting stock.'], 422);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer',
        ]);

        $product = Products::find($request->product_id);

        $newQuantity = (int) $request->quantity;
        \App\Support\StockTransactions::run(function () use ($product, $shopId, $newQuantity) {
            \App\Support\StockTransactions::lock([$product->id], (int) $shopId);
            $current = (int) DB::table('product_stock')->where('product_id', $product->id)->where('shop_id', $shopId)->value('quantity');
            $reserved = (int) DB::table('product_stock')->where('product_id', $product->id)->where('shop_id', $shopId)->value('reserved_quantity');
            if ($reserved > 0 && $newQuantity < $reserved) throw \Illuminate\Validation\ValidationException::withMessages(['quantity'=>'Stock cannot be reduced below the quantity reserved for transfers.']);
            $delta = $newQuantity - $current;
            if ($delta === 0) return;
            DB::table('product_stock')->where('product_id', $product->id)->where('shop_id', $shopId)->update(['quantity' => $newQuantity]);
            DB::table('stock_ins')->insert([
                'shop_id' => $shopId, 'product_id' => $product->id, 'supplier_id' => null,
                'quantity' => $delta, 'cost_price' => 0, 'reference' => 'Inventory count',
                'notes' => 'Quantity set to '.$newQuantity.' via quick stock update.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return $this->stockResponse($product->fresh(),'Stock quantity updated for '.$product->product_name);
    }

    private function stockResponse(Products $product,string $message)
    {
        $shopId=auth()->user()->activeShopId();
        $quantity=(int)DB::table('product_stock')->where('product_id',$product->id)->when($shopId,fn($q)=>$q->where('shop_id',$shopId))->sum('quantity');
        $values=['next_request_key'=>(string)\Illuminate\Support\Str::uuid(),'message'=>$message,'product_id'=>$product->id,'quantity'=>$quantity,'status'=>$quantity<0?'negative':($quantity===0?'zero':'nonzero'),'saleTotal'=>$quantity*$product->price];
        if(auth()->user()->hasAccess('view_cost_prices'))$values['cost']=$quantity*$product->cost_price;
        return response()->json($values)->header('Cache-Control','private, no-store');
    }

    public function history(Request $request)
    {
        $shopId = auth()->user()->activeShopId();

        $query = DB::table('stock_ins')
            ->join('products', 'stock_ins.product_id', '=', 'products.id')
            ->join('shops', 'stock_ins.shop_id', '=', 'shops.id')
            ->select('stock_ins.*', 'products.product_name', 'products.product_code', 'shops.name as shop_name');

        if ($shopId) {
            $query->where('stock_ins.shop_id', $shopId);
        }

        $filterProduct = null;
        if ($request->filled('product_id')) {
            $query->where('stock_ins.product_id', $request->product_id);
            $filterProduct = Products::find($request->product_id);
        }

        $stockIns = $query->orderByDesc('stock_ins.created_at')->limit(300)->get();

        return view('stock.stock_history', compact('stockIns', 'filterProduct'));
    }
}
