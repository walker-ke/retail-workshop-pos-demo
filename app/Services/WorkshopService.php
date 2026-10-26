<?php
namespace App\Services;
use App\Models\User;
use App\Support\StockTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkshopService {
 public const STAGES=['tailor'=>'Tailoring','uppers'=>'Upper making','assembly'=>'Shoe assembly'];
 public const GARMENTS=['longsleeve_shirt'=>'Long-sleeve shirt','shirt'=>'Shirt','tshirt'=>'T-shirt','trouser'=>'Trousers','shorts'=>'Shorts','skirt'=>'Skirt','dress'=>'Dress','tracksuit'=>'Tracksuit','fleece_jacket'=>'Fleece jacket / jumper','overalls'=>'Overalls','dust_coat'=>'Dust coat','blazer'=>'Blazer','sweater'=>'Sweater','sleeveless_sweater'=>'Sleeveless sweater','apron'=>'Apron','pe_kit'=>'PE kit','raincoat'=>'Raincoat'];
 public static function money(string $value): int {if(!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',$value))throw ValidationException::withMessages(['amount'=>'Enter a valid KES amount with at most two decimal places.']);$parts=explode('.',$value);return (int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');}
 private function run(callable $operation){try{return StockTransactions::run($operation);}catch(\Illuminate\Database\QueryException $error){if((int)($error->errorInfo[1]??0)===1062)abort(409,'This operation was already submitted. Refresh before retrying.');throw $error;}}
 private function fail(string $message): never {throw ValidationException::withMessages(['workshop'=>$message]);}
 private function owner(User $actor): void {abort_unless($actor->hasAccess('workshop') && $actor->active,403);}
 private function hash(array $data): string {return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));}
 private function hub(int $id,bool $active=true): object {$hub=DB::table('hubs')->where('id',$id)->lockForUpdate()->first();if(!$hub || ($active && !$hub->is_active))$this->fail('Choose an active hub.');return $hub;}
 private function fundi(int $id,bool $active=true): object {$fundi=DB::table('workshop_fundis')->where('id',$id)->lockForUpdate()->first();if(!$fundi || ($active && !$fundi->is_active))$this->fail('Choose an active fundi.');return $fundi;}
 private function date(string $date): void {$valid=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('Africa/Nairobi'));if(!$valid || $valid->format('Y-m-d')!==$date || $date>now('Africa/Nairobi')->toDateString())$this->fail('Choose a valid date, no later than today.');}
 private function prior(string $table,string $key,string $hash): ?int {$row=DB::table($table)->where('request_key',$key)->first();if(!$row)return null;abort_unless(hash_equals($row->request_hash,$hash),409,'This request was already used with different details.');return $row->id;}
 private function component(int $product,string $kind): int {DB::table('workshop_components')->insertOrIgnore(['product_id'=>$product,'kind'=>$kind,'created_at'=>now(),'updated_at'=>now()]);return (int)DB::table('workshop_components')->where('product_id',$product)->where('kind',$kind)->value('id');}
 private function stock(int $hub,int $component): object {DB::table('workshop_component_stock')->insertOrIgnore(['hub_id'=>$hub,'component_id'=>$component,'quantity'=>0]);return DB::table('workshop_component_stock')->where('hub_id',$hub)->where('component_id',$component)->lockForUpdate()->first();}
 private function componentEvent(User $actor,int $hub,object $stock,int $delta,string $action,?int $production,?string $key=null,?string $hash=null,?string $note=null): void {
  $balance=(int)$stock->quantity+$delta;if($balance<0 || $balance>2147483647)$this->fail('Insufficient components or quantity too large.');
  DB::table('workshop_component_stock')->where('id',$stock->id)->update(['quantity'=>$balance]);
  DB::table('workshop_component_events')->insert(['hub_id'=>$hub,'component_id'=>$stock->component_id,'delta'=>$delta,'balance'=>$balance,'action'=>$action,'production_id'=>$production,'actor_id'=>$actor->id,'actor_name'=>$actor->name,'request_key'=>$key,'request_hash'=>$hash,'note'=>$note,'created_at'=>now()]);
 }
 public function production(User $actor,array $data): int {
  $this->owner($actor);$this->date($data['work_date']);$payload=[$actor->id,(int)$data['fundi_id'],(int)$data['hub_id'],(int)$data['product_id'],$data['stage'],(int)$data['quantity'],$data['work_date']];if(!empty($data['garment_type']))$payload[]=$data['garment_type'];$hash=$this->hash($payload);
  if(!isset(self::STAGES[$data['stage']]) || (int)$data['quantity']<1 || (int)$data['quantity']>999999)$this->fail('Choose a production stage and positive whole quantity.');
  return $this->run(function()use($actor,$data,$hash){
   $hub=$this->hub((int)$data['hub_id'],false);$fundi=$this->fundi((int)$data['fundi_id'],false);
   if($prior=$this->prior('workshop_production',$data['request_key'],$hash))return $prior;
   if(!$hub->is_active || !$fundi->is_active)$this->fail('Choose an active fundi and hub.');
   if(!in_array($data['stage'],json_decode($fundi->skills,true),true))$this->fail('This fundi is not assigned to that production stage.');
   $product=DB::table('products')->where('id',$data['product_id'])->lockForUpdate()->first();if(!$product)$this->fail('Choose an existing product variant.');
   if($data['stage']==='tailor' && (DB::table('workshop_components')->where('product_id',$product->id)->exists() || DB::table('workshop_production')->where('product_id',$product->id)->where('stage','assembly')->exists()))$this->fail('This SKU has shoe components. Select a garment SKU for tailoring.');
   if($data['stage']!=='tailor' && DB::table('workshop_production')->where('product_id',$product->id)->where('stage','tailor')->exists())$this->fail('This SKU is used for garments. Select a shoe SKU.');
   $garment=$data['garment_type']??null;
   if($garment!==null && (!isset(self::GARMENTS[$garment]) || $data['stage']!=='tailor'))$this->fail('Choose a garment type only for tailoring.');
   $rate=(int)$fundi->{$data['stage'].'_rate'};
   $tailoringRates=json_decode($fundi->tailoring_rates??'{}',true)?:[];
   if($data['stage']==='tailor' && $tailoringRates){if(!$garment || !isset($tailoringRates[$garment]))$this->fail('Set this fundi’s rate for the selected garment before recording production.');$rate=(int)$tailoringRates[$garment];}
   $quantity=(int)$data['quantity'];$amount=$quantity*$rate;
   if($rate<=0 || $amount>999999999999)$this->fail('Set a positive rate; total earnings must be within the supported limit.');
   $stocks=[];
   $deductions=[];
   if($data['stage']==='uppers')$stocks[]=$this->stock((int)$data['hub_id'],$this->component($product->id,'uppers'));
   if($data['stage']==='assembly'){
    // The hub lock serializes production and transfers. Consume stock in stable row order.
    $upperStocks=DB::table('workshop_component_stock')->where('hub_id',(int)$data['hub_id'])
     ->whereIn('component_id',DB::table('workshop_components')->where('kind','uppers')->select('id'))
     ->where('quantity','>',0)->orderBy('id')->lockForUpdate()->get();
    if($upperStocks->sum('quantity')<$quantity)$this->fail('Assembly was not saved. '.$hub->name.' has only '.$upperStocks->sum('quantity').' upper pairs available; '.$quantity.' are needed. No stock or earnings changed.');
    $remaining=$quantity;
    foreach($upperStocks as $stock){if(!$remaining)break;$used=min($remaining,(int)$stock->quantity);$stocks[]=$stock;$deductions[$stock->id]=$used;$remaining-=$used;}
   }
   $id=DB::table('workshop_production')->insertGetId(['fundi_id'=>$fundi->id,'fundi_name'=>$fundi->name,'hub_id'=>$data['hub_id'],'product_id'=>$product->id,'product_name'=>$product->product_name,'stage'=>$data['stage'],'garment_type'=>$garment,'work_date'=>$data['work_date'],'quantity'=>$quantity,'rate'=>$rate,'amount'=>$amount,'recipe'=>$data['stage']==='assembly'?'v3: 1 pooled upper pair per finished pair; all upper types in output hub':null,'actor_id'=>$actor->id,'actor_name'=>$actor->name,'request_key'=>$data['request_key'],'request_hash'=>$hash,'created_at'=>now()]);
   foreach($stocks as $stock)$this->componentEvent($actor,(int)$data['hub_id'],$stock,$data['stage']==='assembly'?-$deductions[$stock->id]:$quantity,$data['stage'],$id);
   if($data['stage']!=='uppers'){
    DB::table('hub_stock')->insertOrIgnore(['hub_id'=>$data['hub_id'],'product_id'=>$product->id,'quantity'=>0]);
    $stock=DB::table('hub_stock')->where('hub_id',$data['hub_id'])->where('product_id',$product->id)->lockForUpdate()->first();$balance=(int)$stock->quantity+$quantity;if($balance>2147483647)$this->fail('Hub stock quantity is too large.');
    DB::table('hub_stock')->where('id',$stock->id)->update(['quantity'=>$balance]);DB::table('hub_stock_events')->insert(['hub_id'=>$data['hub_id'],'product_id'=>$product->id,'delta'=>$quantity,'balance'=>$balance,'action'=>'production','note'=>'Workshop production #'.$id,'user_id'=>$actor->id,'actor_name'=>$actor->name,'created_at'=>now()]);
   }
   DB::table('workshop_ledger')->insert(['fundi_id'=>$fundi->id,'entry_date'=>$data['work_date'],'type'=>'earning','amount'=>$amount,'description'=>self::STAGES[$data['stage']].($garment?' · '.self::GARMENTS[$garment]:'').' · '.$product->product_name,'production_id'=>$id,'actor_id'=>$actor->id,'actor_name'=>$actor->name,'created_at'=>now()]);return $id;
  });
 }
 public function payment(User $actor,array $data): int {
  $this->owner($actor);$this->date($data['payment_date']);$amount=self::money((string)$data['amount']);if($amount<=0 || !in_array($data['type'],['advance','payout'],true))$this->fail('Choose a payment type and positive amount.');
  $hash=$this->hash([$actor->id,(int)$data['fundi_id'],$data['type'],$amount,$data['payment_date'],$data['method'],$data['reference']??null]);
  return $this->run(function()use($actor,$data,$hash,$amount){$fundi=$this->fundi((int)$data['fundi_id'],false);if($prior=$this->prior('workshop_payments',$data['request_key'],$hash))return $prior;if(!$fundi->is_active)$this->fail('This fundi is archived.');
   $balance=(int)DB::table('workshop_ledger')->where('fundi_id',$fundi->id)->sum('amount');if($data['type']==='payout' && $amount>max(0,$balance))$this->fail('This payout exceeds the amount owed. Use an advance for additional funding.');
   $id=DB::table('workshop_payments')->insertGetId(['fundi_id'=>$fundi->id,'fundi_name'=>$fundi->name,'type'=>$data['type'],'amount'=>$amount,'payment_date'=>$data['payment_date'],'method'=>$data['method'],'reference'=>$data['reference']??null,'actor_id'=>$actor->id,'actor_name'=>$actor->name,'request_key'=>$data['request_key'],'request_hash'=>$hash,'created_at'=>now()]);
   DB::table('workshop_ledger')->insert(['fundi_id'=>$fundi->id,'entry_date'=>$data['payment_date'],'type'=>$data['type'],'amount'=>-$amount,'description'=>ucfirst($data['type']).' · '.$data['method'].(!empty($data['reference'])?' · '.$data['reference']:''),'payment_id'=>$id,'actor_id'=>$actor->id,'actor_name'=>$actor->name,'created_at'=>now()]);return $id;
  });
 }
 public function components(User $actor,array $data): void {
  $this->owner($actor);if(!in_array($data['kind'],['uppers','soles'],true) || (int)$data['quantity']<1 || (int)$data['quantity']>999999)$this->fail('Choose component type and positive whole pairs.');
  $hash=$this->hash([$actor->id,(int)$data['hub_id'],(int)$data['product_id'],$data['kind'],(int)$data['quantity'],$data['note']]);
  $this->run(function()use($actor,$data,$hash){$hub=$this->hub((int)$data['hub_id'],false);if($this->prior('workshop_component_events',$data['request_key'],$hash))return;if(!$hub->is_active)$this->fail('This hub is archived.');if(!DB::table('products')->where('id',$data['product_id'])->lockForUpdate()->first())$this->fail('Choose an existing product.');if(DB::table('workshop_production')->where('product_id',$data['product_id'])->where('stage','tailor')->exists())$this->fail('This SKU is used for garments, not shoe components.');$component=$this->component((int)$data['product_id'],$data['kind']);$stock=$this->stock((int)$data['hub_id'],$component);$this->componentEvent($actor,(int)$data['hub_id'],$stock,(int)$data['quantity'],'stock_received',null,$data['request_key'],$hash,$data['note']);});
 }
}
