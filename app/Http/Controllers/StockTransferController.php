<?php
namespace App\Http\Controllers;

use App\Models\Products;
use App\Models\Shop;
use App\Services\StockTransferService;
use App\Support\StockTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    public function alerts(Request $request)
    {
        $user = $request->user(); $shop = $user->activeShopId();
        $count = DB::table('stock_transfers')->where(function ($q) use ($user, $shop) {
            $q->where(function ($incoming) use ($user, $shop) { $incoming->where('status','requested'); if (!$user->isTopAdmin()) $incoming->where('from_shop_id',$shop); })
              ->orWhere(function ($arriving) use ($user, $shop) { $arriving->where('status','dispatched'); if (!$user->isTopAdmin()) $arriving->where('to_shop_id',$shop); });
        })->count();
                $count += DB::table('hub_deliveries')->where('status',$user->isTopAdmin()?'reported':'dispatched')->when(!$user->isTopAdmin(),fn($q)=>$q->where('shop_id',$shop))->count();
        return response()->json(['count'=>$count]);
    }

    public function index(Request $request)
    {
        $request->validate(['q'=>'nullable|string|max:100', 'status'=>['nullable', Rule::in(['requested','accepted','dispatched','received','declined','cancelled'])]]);
        $user = $request->user(); $shopId = $user->activeShopId();
        $shops = Shop::where('is_active', true)->orderBy('name')->get();
        $products = DB::table('products')->orderBy('product_name')->get(['id','product_name','product_code','brand']);
        $stock = DB::table('product_stock')->whereIn('shop_id', $shops->pluck('id'))->get(['product_id','shop_id','quantity','reserved_quantity']);
        $catalog = [
            'products'=>$products->map(fn ($p) => ['id'=>$p->id,'name'=>$p->product_name,'code'=>$p->product_code,'brand'=>$p->brand]),
            'shops'=>$shops->map(fn ($s) => ['id'=>$s->id,'name'=>$s->name]),
            'stock'=>$stock, 'receivingShopId'=>$shopId,
        ];
        $transfers = DB::table('stock_transfers as t')->join('products as p','p.id','=','t.product_id')->join('shops as f','f.id','=','t.from_shop_id')->join('shops as d','d.id','=','t.to_shop_id')
            ->select('t.*','p.product_name','f.name as from_name','d.name as to_name');
        if (!$user->isTopAdmin()) $transfers->where(fn ($q) => $q->where('t.from_shop_id', $shopId)->orWhere('t.to_shop_id', $shopId));
        if ($request->filled('status')) $transfers->where('t.status', $request->status);
        $transfers = $transfers->orderByDesc('t.updated_at')->orderByDesc('t.id')->paginate(10, ['*'], 'requests_page')->withQueryString();
                $hubDeliveries=HubController::deliveries()->when(!$user->isTopAdmin(),fn($q)=>$q->where('d.shop_id',$shopId))->orderByRaw("CASE WHEN d.status = 'dispatched' THEN 0 WHEN d.status = 'reported' THEN 1 ELSE 2 END")->orderByDesc('d.id')->paginate(20,['*'],'hub_page')->withQueryString();
        return view('stock.transfers', compact('shopId','shops','catalog','transfers','hubDeliveries'));
    }

    public function store(Request $request, StockTransferService $service)
    {
        $data = $request->validate(['request_key'=>'required|uuid', 'product_id'=>'required|integer|exists:products,id', 'from_shop_id'=>['required','integer', Rule::exists('shops','id')->where('is_active', true)], 'quantity'=>'required|integer|min:1|max:999999', 'note'=>'nullable|string|max:500']);
        $actor = $request->user(); $to = $actor->activeShopId();
        if (!$to || !Shop::where('id', $to)->where('is_active', true)->exists()) throw ValidationException::withMessages(['shop'=>'Select an active receiving shop first.']);
        if ((int) $data['from_shop_id'] === $to) throw ValidationException::withMessages(['shop'=>'Choose a different supplying shop.']);
        StockTransactions::run(function () use ($data, $actor, $to, $service) {
            // Lock the receiving balance so a simultaneous receipt cannot bypass the low-stock rule.
            StockTransactions::lock([(int) $data['product_id']], $to);
            $ownQuantity = (int) DB::table('product_stock')->where('shop_id',$to)->where('product_id',$data['product_id'])->value('quantity');
            $existing = DB::table('stock_transfers')->where('request_key',$data['request_key'])->exists();
            if (!$existing && $ownQuantity > 5) throw ValidationException::withMessages(['stock'=>'You can request this item only when your shop has 5 or fewer. Your shop currently has '.$ownQuantity.'.']);
            $inserted = DB::table('stock_transfers')->insertOrIgnore(['request_key'=>$data['request_key'], 'product_id'=>$data['product_id'], 'from_shop_id'=>$data['from_shop_id'], 'to_shop_id'=>$to, 'requested_quantity'=>$data['quantity'], 'status'=>'requested', 'note'=>$data['note'] ?? null, 'created_at'=>now(), 'updated_at'=>now()]);
            $transfer = DB::table('stock_transfers')->where('request_key', $data['request_key'])->first();
            abort_unless($transfer && (int) $transfer->to_shop_id === $to && (int) $transfer->from_shop_id === (int) $data['from_shop_id'] && (int) $transfer->product_id === (int) $data['product_id'] && (int) $transfer->requested_quantity === (int) $data['quantity'], 409);
            if ($inserted) $service->event($transfer->id, 'request', $actor, (int) $data['quantity'], $data['note'] ?? null);
        });
        return redirect()->route('transfers.index')->with('success', 'Stock request sent. The supplying shop must acknowledge it.');
    }

    public function receiveHub(Request $request,int $id,\App\Services\HubStockService $service)
    {
        $data=$request->validate(['quantity'=>'required|integer|min:0|max:999999','request_key'=>'required|uuid','note'=>'nullable|string|max:500']);
        $service->receive($request->user(),$id,(int)$data['quantity'],$data['request_key'],$data['note']??null);
        return redirect()->route('transfers.index')->with('success','Delivery confirmation saved. Only the quantity you received was added to your shop.');
    }

    public function update(Request $request, int $id, StockTransferService $service)
    {
        $data = $request->validate(['action'=>['required', Rule::in(['approve','decline','dispatch','receive','cancel'])], 'quantity'=>'required_if:action,approve|nullable|integer|min:1|max:999999', 'note'=>'nullable|string|max:500']);
        $service->change($id, $data['action'], $request->user(), isset($data['quantity']) ? (int) $data['quantity'] : null, $data['note'] ?? null);
        return redirect()->route('transfers.index')->with('success', 'Transfer updated. Stock balances have been updated where applicable.');
    }
}
