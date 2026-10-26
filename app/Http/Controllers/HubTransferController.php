<?php
namespace App\Http\Controllers;
use App\Services\HubTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class HubTransferController extends Controller {
 public function __construct(){$this->middleware('auth');$this->middleware(function($request,$next){abort_unless($request->user()->active && $request->user()->hasAccess('hubs'),403);return $next($request);});}
 public function index(Request $request){
  $data=$request->validate(['hub_id'=>'nullable|integer|exists:hubs,id','status'=>['nullable',Rule::in(['in_transit','received','cancelled'])]]);
  $hubs=DB::table('hubs')->where('is_active',true)->orderBy('name')->get();$hubId=(int)($data['hub_id']??$hubs->first()?->id??0);$hub=$hubs->firstWhere('id',$hubId);abort_unless($hub,404);
  $products=DB::table('products')->orderBy('product_name')->get(['id','product_name as name','product_code as code','brand']);$balances=DB::table('hub_stock')->where('hub_id',$hubId)->pluck('quantity','product_id');
  $componentBalances=DB::table('workshop_component_stock as s')->join('workshop_components as c','c.id','=','s.component_id')->where('s.hub_id',$hubId)->get(['c.product_id','c.kind','s.quantity']);
  $stocks=['finished'=>$balances,'uppers'=>[],'soles'=>[]];foreach($componentBalances as $row)$stocks[$row->kind][$row->product_id]=(int)$row->quantity;
  $transfers=DB::table('hub_transfers as t')->join('hubs as a','a.id','=','t.source_hub_id')->join('hubs as b','b.id','=','t.destination_hub_id')->join('products as p','p.id','=','t.product_id')->where(fn($q)=>$q->where('t.source_hub_id',$hubId)->orWhere('t.destination_hub_id',$hubId))->when($request->filled('status'),fn($q)=>$q->where('t.status',$data['status']))->select('t.*','a.name as source_name','b.name as destination_name','p.product_name')->orderByDesc('t.id')->paginate(10)->withQueryString();
  return view('hubs.transfer',compact('hubs','hub','hubId','products','balances','stocks','transfers'));
 }
 public function send(Request $request,int $id,HubTransferService $service){
  $data=$request->validate(['destination_hub_id'=>'required|integer|exists:hubs,id','product_id'=>'required|integer|exists:products,id','kind'=>['required',Rule::in(array_keys(HubTransferService::KINDS))],'quantity'=>'required|integer|min:1|max:999999','request_key'=>'required|uuid']);
  $transfer=$service->send($request->user(),$id,(int)$data['destination_hub_id'],(int)$data['product_id'],$data['kind'],(int)$data['quantity'],$data['request_key']);return redirect()->route('hubs.transfer-page',['hub_id'=>$id])->with('success','Transfer #'.$transfer.' sent. Confirm receipt when it arrives at the receiving hub.');
 }
 public function complete(Request $request,int $id,HubTransferService $service){$data=$request->validate(['action'=>['required',Rule::in(['receive','cancel'])],'request_key'=>'required|uuid']);$service->complete($request->user(),$id,$data['action'],$data['request_key']);return redirect()->back()->with('success',$data['action']==='receive'?'Receipt confirmed. Receiving hub stock updated.':'Transfer cancelled. Stock returned to the sending hub.');}
}
