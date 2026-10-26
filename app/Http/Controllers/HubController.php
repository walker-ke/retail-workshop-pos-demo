<?php
namespace App\Http\Controllers;
use App\Services\HubStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HubController extends Controller
{
    public function __construct(){ $this->middleware('auth');$this->middleware(function($request,$next){abort_unless($request->user()->active && $request->user()->hasAccess('hubs'),403);return $next($request);}); }
    public function index(Request $request){
        $request->validate(['hub_id'=>'nullable|integer|exists:hubs,id','q'=>'nullable|string|max:100']);
        $hubs=DB::table('hubs')->orderByDesc('is_active')->orderBy('name')->get();
        $hub=$request->filled('hub_id')?$hubs->firstWhere('id',(int)$request->hub_id):$hubs->firstWhere('is_active',1);
        $hubId=$hub?->id;
        $inventory=DB::table('hub_stock as s')->join('products as p','p.id','=','s.product_id')->where('hub_id',$hubId??0)->select('p.product_name','s.quantity')->when($request->filled('q'),fn($q)=>$q->where('p.product_name','like','%'.$request->q.'%'))->orderBy('p.product_name')->paginate(50,['*'],'stock_page')->withQueryString();
        $deliveries=self::deliveries()->when($hubId,fn($q)=>$q->where('d.hub_id',$hubId))->orderByDesc('d.id')->paginate(20,['*'],'deliveries_page')->withQueryString();
        $events=DB::table('hub_stock_events as e')->join('products as p','p.id','=','e.product_id')->where('e.hub_id',$hubId??0)->select('e.*','p.product_name')->orderByDesc('e.id')->limit(30)->get();
        $finishedUnits=(int)DB::table('hub_stock')->where('hub_id',$hubId??0)->sum('quantity');
        $componentTotals=DB::table('workshop_component_stock as s')->join('workshop_components as c','c.id','=','s.component_id')->where('s.hub_id',$hubId??0)->select('c.kind',DB::raw('SUM(s.quantity) as quantity'))->groupBy('c.kind')->pluck('quantity','c.kind');
        $pendingShop=(int)DB::table('hub_deliveries')->where('hub_id',$hubId??0)->whereIn('status',['dispatched','reported'])->count();
        $pendingTransfers=(int)DB::table('hub_transfers')->where('status','in_transit')->where(fn($q)=>$q->where('source_hub_id',$hubId??0)->orWhere('destination_hub_id',$hubId??0))->count();
        return view('hubs.index',compact('hubs','hub','hubId','inventory','deliveries','events','finishedUnits','componentTotals','pendingShop','pendingTransfers'));
    }
    public function stockForm(Request $request,?int $id=null){return $this->form($id??$this->selectedHub($request),'stock');}
    public function sendForm(Request $request,?int $id=null){return $this->form($id??$this->selectedHub($request),'send');}
    private function selectedHub(Request $request): int {
        $request->validate(['hub_id'=>'nullable|integer|exists:hubs,id']);
        return (int)($request->input('hub_id')??DB::table('hubs')->where('is_active',true)->orderBy('name')->value('id'));
    }
    private function form(int $id,string $mode){
        $hub=DB::table('hubs')->where('id',$id)->where('is_active',true)->first();
        abort_unless($hub,404);
        $hubId=$hub->id;
        $products=DB::table('products')->orderBy('product_name')->get(['id','product_name as name','product_code as code','brand']);
        $balances=DB::table('hub_stock')->where('hub_id',$hubId)->pluck('quantity','product_id');
        $shops=$mode==='send'?DB::table('shops')->where('is_active',true)->orderBy('name')->get(['id','name']):collect();
        $hubs=DB::table('hubs')->where('is_active',true)->orderBy('name')->get(['id','name']);
        return view('hubs.'.$mode,compact('hub','hubId','products','balances','shops','hubs'));
    }
    public function productStock(int $id,int $product){
        $hub=DB::table('hubs')->where('id',$id)->where('is_active',true)->first();abort_unless($hub,404);
        abort_unless(DB::table('products')->where('id',$product)->exists(),404);
        if(request()->boolean('hub_only'))return response()->json(['hub_quantity'=>(int)(DB::table('hub_stock')->where('hub_id',$id)->where('product_id',$product)->value('quantity')??0)])->header('Cache-Control','private, no-store');
        $shops=DB::table('shops as s')->leftJoin('product_stock as ps',function($join)use($product){$join->on('ps.shop_id','=','s.id')->where('ps.product_id',$product);})
            ->where('s.is_active',true)->orderBy('s.name')->get(['s.name','ps.quantity','ps.reserved_quantity'])->map(function($shop){
                $quantity=(int)($shop->quantity??0);$reserved=(int)($shop->reserved_quantity??0);
                return ['name'=>$shop->name,'quantity'=>$quantity,'reserved'=>$reserved,'available'=>max(0,$quantity-$reserved)];
            });
        return response()->json(['hub_quantity'=>(int)(DB::table('hub_stock')->where('hub_id',$id)->where('product_id',$product)->value('quantity')??0),'shops'=>$shops])->header('Cache-Control','private, no-store');
    }
    public static function deliveries(){return DB::table('hub_deliveries as d')->join('hubs as h','h.id','=','d.hub_id')->join('shops as s','s.id','=','d.shop_id')->join('products as p','p.id','=','d.product_id')->select('d.*','h.name as hub_name','s.name as shop_name','p.product_name');}
    public function save(Request $request,?int $id=null){
        $request->validate(['name'=>'required|string|max:100']);
        $request->merge(['name'=>Str::upper(preg_replace('/\s+/u',' ',trim($request->input('name'))))]);
        $data=$request->validate(['name'=>['required','string','max:100',Rule::unique('hubs','name')->ignore($id)]]);
        if($id){$row=DB::table('hubs')->where('id',$id)->first();abort_unless($row,404);DB::table('hubs')->where('id',$id)->update($data+['updated_at'=>now()]);}
        else $id=DB::table('hubs')->insertGetId($data+['is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        return redirect()->route('hubs.index',['hub_id'=>$id])->with('success','Hub saved.');
    }
    public function stock(Request $request,int $id,HubStockService $service){
        $data=$request->validate(['product_id'=>'required|integer|exists:products,id','quantity'=>'required|integer|min:0|max:999999','action'=>['required',Rule::in(['add','count'])],'request_key'=>'required|uuid','expected_quantity'=>'required_if:action,count|nullable|integer|min:0|max:2147483647']);
        $service->stock($request->user(),$id,(int)$data['product_id'],(int)$data['quantity'],$data['action'],$data['request_key'],null,isset($data['expected_quantity'])?(int)$data['expected_quantity']:null);
        return redirect()->route('hubs.stock-page',['hub_id'=>$id])->with('success','Hub stock updated.');
    }
    public function send(Request $request,int $id,HubStockService $service){
        $data=$request->validate(['product_id'=>'required|integer|exists:products,id','shop_id'=>['required','integer',Rule::exists('shops','id')->where('is_active',true)],'quantity'=>'required|integer|min:1|max:999999','request_key'=>'required|uuid','note'=>'nullable|string|max:500']);
        $delivery=$service->send($request->user(),$id,(int)$data['shop_id'],(int)$data['product_id'],(int)$data['quantity'],$data['request_key'],$data['note']??null);
        return redirect()->route('hubs.index',['hub_id'=>$id])->with('success','Delivery #'.$delivery.' sent. The shop must confirm receipt.');
    }
    public function archive(Request $request,int $id,HubStockService $service){$service->archive($request->user(),$id);return redirect()->route('hubs.index')->with('success','Hub removed from use. Its records have been kept.');}
    public function resolve(Request $request,int $id,HubStockService $service){
        $data=$request->validate(['resolution'=>['required',Rule::in(['returned','written_off'])],'request_key'=>'required|uuid','note'=>'required|string|max:500']);
        $service->resolve($request->user(),$id,$data['resolution'],$data['request_key'],$data['note']);
        return redirect()->back()->with('success','Missing stock resolved.');
    }
}
