<?php
namespace App\Services;
use App\Models\User;
use App\Support\StockTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HubTransferService {
 public const KINDS=['finished'=>'Finished goods','uppers'=>'Uppers (pairs)','soles'=>'Soles (pairs)'];
 private function fail(string $message): never {throw ValidationException::withMessages(['transfer'=>$message]);}
 private function owner(User $actor): void {abort_unless($actor->active && $actor->hasAccess('hubs'),403);}
 private function run(callable $operation){try{return StockTransactions::run($operation);}catch(\Illuminate\Database\QueryException $e){if((int)($e->errorInfo[1]??0)===1062)abort(409,'This operation key was already used. Refresh before retrying.');throw $e;}}
 private function hubs(int $source,int $destination){return DB::table('hubs')->whereIn('id',[$source,$destination])->orderBy('id')->lockForUpdate()->get()->keyBy('id');}
 private function stock(int $hub,int $product,string $kind): array {
  if($kind==='finished'){$table='hub_stock';$where=['hub_id'=>$hub,'product_id'=>$product];}
  else {DB::table('workshop_components')->insertOrIgnore(['product_id'=>$product,'kind'=>$kind,'created_at'=>now(),'updated_at'=>now()]);$component=DB::table('workshop_components')->where('product_id',$product)->where('kind',$kind)->value('id');$table='workshop_component_stock';$where=['hub_id'=>$hub,'component_id'=>$component];}
  DB::table($table)->insertOrIgnore($where+['quantity'=>0]);return [$table,DB::table($table)->where($where)->lockForUpdate()->first()];
 }
 private function move(User $actor,int $hub,int $product,string $kind,int $delta,string $action,int $transfer): void {
  [$table,$stock]=$this->stock($hub,$product,$kind);$balance=(int)$stock->quantity+$delta;
  if($balance<0)$this->fail('The sending hub does not have enough of this stock.');
  if($balance>2147483647)$this->fail('The receiving stock quantity is too large.');
  DB::table($table)->where('id',$stock->id)->update(['quantity'=>$balance]);
  $event=['hub_id'=>$hub,'delta'=>$delta,'balance'=>$balance,'action'=>$action,'note'=>'Hub transfer #'.$transfer,'actor_name'=>$actor->name,'created_at'=>now()];
  if($kind==='finished')DB::table('hub_stock_events')->insert($event+['product_id'=>$product,'user_id'=>$actor->id]);
  else DB::table('workshop_component_events')->insert($event+['component_id'=>$stock->component_id,'actor_id'=>$actor->id]);
 }
 public function send(User $actor,int $source,int $destination,int $product,string $kind,int $quantity,string $key): int {
  $this->owner($actor);if($source===$destination)$this->fail('Choose a different destination hub.');if(!isset(self::KINDS[$kind]) || $quantity<1 || $quantity>999999)$this->fail('Choose a stock type and a positive whole quantity.');
  $hash=hash('sha256',json_encode([$actor->id,$source,$destination,$product,$kind,$quantity]));
  return $this->run(function()use($actor,$source,$destination,$product,$kind,$quantity,$key,$hash){
   $hubs=$this->hubs($source,$destination);
   $prior=DB::table('hub_transfers')->where('request_key',$key)->first();if($prior){abort_unless(hash_equals($prior->request_hash,$hash),409);return (int)$prior->id;}
   if($hubs->count()!==2 || !$hubs[$source]->is_active || !$hubs[$destination]->is_active)$this->fail('Choose two active hubs.');
   abort_unless(DB::table('products')->where('id',$product)->lockForUpdate()->first(),404);
   if($kind!=='finished' && DB::table('workshop_production')->where('product_id',$product)->where('stage','tailor')->exists())$this->fail('This product is used for garments, not shoe components.');
   $id=DB::table('hub_transfers')->insertGetId(['source_hub_id'=>$source,'destination_hub_id'=>$destination,'product_id'=>$product,'kind'=>$kind,'quantity'=>$quantity,'status'=>'in_transit','created_by'=>$actor->id,'sender_name'=>$actor->name,'request_key'=>$key,'request_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]);
   $this->move($actor,$source,$product,$kind,-$quantity,'transfer_out',$id);return $id;
  });
 }
 public function complete(User $actor,int $id,string $action,string $key): void {
  $this->owner($actor);if(!in_array($action,['receive','cancel'],true))$this->fail('Choose receive or cancel.');$hash=hash('sha256',json_encode([$actor->id,$id,$action]));
  $this->run(function()use($actor,$id,$action,$key,$hash){
   $transfer=DB::table('hub_transfers')->where('id',$id)->lockForUpdate()->first();abort_unless($transfer,404);
   if($transfer->complete_key===$key){abort_unless(hash_equals($transfer->complete_hash,$hash),409);return;}
   if($transfer->status!=='in_transit')$this->fail('This transfer has already been completed.');
   $this->hubs((int)$transfer->source_hub_id,(int)$transfer->destination_hub_id);
   abort_unless(DB::table('products')->where('id',$transfer->product_id)->lockForUpdate()->first(),404);
   $hub=$action==='receive'?$transfer->destination_hub_id:$transfer->source_hub_id;
   $this->move($actor,(int)$hub,(int)$transfer->product_id,$transfer->kind,(int)$transfer->quantity,$action==='receive'?'transfer_in':'transfer_return',$id);
   DB::table('hub_transfers')->where('id',$id)->update(['status'=>$action==='receive'?'received':'cancelled','completed_by'=>$actor->id,'completer_name'=>$actor->name,'complete_key'=>$key,'complete_hash'=>$hash,'completed_at'=>now(),'updated_at'=>now()]);
  });
 }
}
