<?php
namespace App\Services;
use App\Models\User;
use App\Support\StockTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HubStockService
{
    private function run(callable $operation) {
        try{return StockTransactions::run($operation);}
        catch(\Illuminate\Database\QueryException $error){if((int)($error->errorInfo[1]??0)===1062)abort(409,'This operation key has already been used.');throw $error;}
    }
    private function owner(User $actor): void {abort_unless($actor->hasAccess('hubs') && $actor->active,403);}
    private function fail(string $message): never {throw ValidationException::withMessages(['hub'=>$message]);}
    private function fingerprint(array $values): string {return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));}
    private function hub(int $id): object {
        $hub=DB::table('hubs')->where('id',$id)->lockForUpdate()->first();abort_unless($hub,404);return $hub;
    }
    private function balance(int $hub,int $product): object {
        DB::table('hub_stock')->insertOrIgnore(['hub_id'=>$hub,'product_id'=>$product,'quantity'=>0]);
        $stock=DB::table('hub_stock')->where('hub_id',$hub)->where('product_id',$product)->lockForUpdate()->first();
        if(!$stock)$this->fail('This product is no longer available. Refresh the hub.');
        return $stock;
    }
    private function event(User $actor,int $hub,int $product,int $delta,int $balance,string $action,?string $note,?int $delivery=null,?string $key=null,?string $hash=null): void {
        $values=['hub_id'=>$hub,'product_id'=>$product,'delta'=>$delta,'balance'=>$balance,'action'=>$action,'note'=>$note,'delivery_id'=>$delivery,'user_id'=>$actor->id,'actor_name'=>$actor->name,'request_key'=>$key,'request_hash'=>$hash,'created_at'=>now()];
        if($key){abort_unless(DB::table('hub_stock_events')->insertOrIgnore($values),409);}else DB::table('hub_stock_events')->insert($values);
    }
    public function stock(User $actor,int $hubId,int $product,int $quantity,string $action,string $key,?string $note,?int $expectedQuantity=null): void {
        $this->owner($actor);
        if(!in_array($action,['add','count'],true) || $quantity<0 || ($action==='add' && !$quantity) || $quantity>999999)$this->fail('Enter a valid stock quantity.');
        $hash=$this->fingerprint([$actor->id,$hubId,$product,$quantity,$action,$note,$expectedQuantity]);
        $this->run(function()use($actor,$hubId,$product,$quantity,$action,$key,$note,$hash,$expectedQuantity){
            $hub=$this->hub($hubId);
            $prior=DB::table('hub_stock_events')->where('request_key',$key)->first();
            if($prior){abort_unless(hash_equals($prior->request_hash,$hash),409);return;}
            if(!$hub->is_active)$this->fail('This hub has been removed from use.');
            $stock=$this->balance($hubId,$product);
            if($action==='count' && $expectedQuantity!==null && (int)$stock->quantity!==$expectedQuantity)$this->fail('Hub stock changed while you were counting. Open the product again and check the current quantity.');
            $after=$action==='add'?$stock->quantity+$quantity:$quantity;
            if($after>2147483647)$this->fail('This quantity is too large.');
            DB::table('hub_stock')->where('id',$stock->id)->update(['quantity'=>$after]);
            $this->event($actor,$hubId,$product,$after-$stock->quantity,$after,$action,$note,null,$key,$hash);
        });
    }
    public function send(User $actor,int $hubId,int $shop,int $product,int $quantity,string $key,?string $note): int {
        $this->owner($actor);if($quantity<1 || $quantity>999999)$this->fail('Enter a quantity to send.');
        $hash=$this->fingerprint([$actor->id,$hubId,$shop,$product,$quantity,$note]);
        return $this->run(function()use($actor,$hubId,$shop,$product,$quantity,$key,$note,$hash){
            $hub=$this->hub($hubId);
            $prior=DB::table('hub_deliveries')->where('request_key',$key)->first();
            if($prior){abort_unless(hash_equals($prior->request_hash,$hash),409);return (int)$prior->id;}
            if(!$hub->is_active)$this->fail('This hub has been removed from use.');
            $destination=DB::table('shops')->where('id',$shop)->lockForUpdate()->first();
            if(!$destination || !$destination->is_active)$this->fail('Choose an active destination shop.');
            $stock=$this->balance($hubId,$product);
            if($stock->quantity<$quantity)$this->fail('The hub does not have enough stock to send.');
            $inserted=DB::table('hub_deliveries')->insertOrIgnore(['request_key'=>$key,'request_hash'=>$hash,'hub_id'=>$hubId,'shop_id'=>$shop,'product_id'=>$product,'quantity'=>$quantity,'created_by'=>$actor->id,'sender_name'=>$actor->name,'status'=>'dispatched','note'=>$note,'created_at'=>now(),'updated_at'=>now()]);
            $saved=DB::table('hub_deliveries')->where('request_key',$key)->first();abort_unless($saved && hash_equals($saved->request_hash,$hash),409);$id=(int)$saved->id;if(!$inserted)return $id;
            DB::table('hub_stock')->where('id',$stock->id)->decrement('quantity',$quantity);
            $this->event($actor,$hubId,$product,-$quantity,$stock->quantity-$quantity,'send',$note,$id);
            return $id;
        });
    }
    public function receive(User $actor,int $id,int $quantity,string $key,?string $note): void {
        $this->run(function()use($actor,$id,$quantity,$key,$note){
            $delivery=DB::table('hub_deliveries')->where('id',$id)->lockForUpdate()->first();abort_unless($delivery,404);
            abort_unless($actor->active && in_array((int)$actor->access_level,[User::CASHIER,User::MANAGER],true) && $actor->activeShopId()===(int)$delivery->shop_id && (int)$actor->id!==(int)$delivery->created_by,403);
            if($delivery->receive_key===$key){abort_unless((int)$delivery->received_by===(int)$actor->id && (int)$delivery->received_quantity===$quantity && $delivery->receipt_note===$note,409);return;}
            if($delivery->status!=='dispatched')$this->fail('This delivery has already been confirmed.');
            if($quantity<0 || $quantity>$delivery->quantity)$this->fail('Confirm only the quantity actually delivered.');
            if($quantity<$delivery->quantity && !trim($note??''))$this->fail('Explain the missing or damaged items.');
            $this->hub((int)$delivery->hub_id);
            StockTransactions::lock([(int)$delivery->product_id],(int)$delivery->shop_id);
            $current=(int)DB::table('product_stock')->where('shop_id',$delivery->shop_id)->where('product_id',$delivery->product_id)->value('quantity');
            if($current+$quantity>2147483647)$this->fail('Shop stock quantity is too large.');
            if($quantity){
                DB::table('product_stock')->where('shop_id',$delivery->shop_id)->where('product_id',$delivery->product_id)->increment('quantity',$quantity);
                DB::table('stock_ins')->insert(['shop_id'=>$delivery->shop_id,'product_id'=>$delivery->product_id,'quantity'=>$quantity,'cost_price'=>0,'reference'=>'Hub delivery #'.$id,'notes'=>'Received by '.$actor->name,'created_at'=>now(),'updated_at'=>now()]);
            }
            DB::table('hub_deliveries')->where('id',$id)->update(['status'=>$quantity===(int)$delivery->quantity?'received':'reported','received_quantity'=>$quantity,'received_by'=>$actor->id,'receiver_name'=>$actor->name,'receive_key'=>$key,'receipt_note'=>$note,'received_at'=>now(),'updated_at'=>now()]);
            $balance=$this->balance((int)$delivery->hub_id,(int)$delivery->product_id);
            $this->event($actor,(int)$delivery->hub_id,(int)$delivery->product_id,0,(int)$balance->quantity,'receive',$note,$id);
        });
    }
    public function resolve(User $actor,int $id,string $resolution,string $key,string $note): void {
        $this->owner($actor);if(!in_array($resolution,['returned','written_off'],true) || !trim($note))$this->fail('Choose what happened to the missing stock and give a reason.');
        $hash=$this->fingerprint([$actor->id,$id,$resolution,$note]);
        $this->run(function()use($actor,$id,$resolution,$key,$note,$hash){
            $delivery=DB::table('hub_deliveries')->where('id',$id)->lockForUpdate()->first();abort_unless($delivery,404);
            if($delivery->resolve_key===$key){abort_unless(hash_equals($delivery->resolve_hash,$hash),409);return;}
            if($delivery->status!=='reported')$this->fail('Only a reported delivery can be resolved.');
            $this->hub((int)$delivery->hub_id);$balance=$this->balance((int)$delivery->hub_id,(int)$delivery->product_id);
            $missing=(int)$delivery->quantity-(int)$delivery->received_quantity;$delta=$resolution==='returned'?$missing:0;
            if($balance->quantity+$delta>2147483647)$this->fail('Hub stock quantity is too large.');
            if($delta)DB::table('hub_stock')->where('id',$balance->id)->increment('quantity',$delta);
            DB::table('hub_deliveries')->where('id',$id)->update(['status'=>'resolved','resolution'=>$resolution,'resolution_note'=>$note,'resolve_key'=>$key,'resolve_hash'=>$hash,'resolved_by'=>$actor->id,'resolver_name'=>$actor->name,'resolved_at'=>now(),'updated_at'=>now()]);
            $this->event($actor,(int)$delivery->hub_id,(int)$delivery->product_id,$delta,$balance->quantity+$delta,$resolution,$note,$id);
        });
    }
    public function archive(User $actor,int $id): void {
        $this->owner($actor);$this->run(function()use($id){
            $this->hub($id);
            if((\Illuminate\Support\Facades\Schema::hasTable('hub_transfers') && DB::table('hub_transfers')->where('status','in_transit')->where(fn($q)=>$q->where('source_hub_id',$id)->orWhere('destination_hub_id',$id))->exists()) || (\Illuminate\Support\Facades\Schema::hasTable('workshop_component_stock') && DB::table('workshop_component_stock')->where('hub_id',$id)->where('quantity','!=',0)->exists()) || DB::table('hub_stock')->where('hub_id',$id)->where('quantity','!=',0)->exists() || DB::table('hub_deliveries')->where('hub_id',$id)->whereIn('status',['dispatched','reported'])->exists())$this->fail('Clear this hub’s stock and finish its deliveries before removing it.');
            DB::table('hubs')->where('id',$id)->update(['is_active'=>false,'updated_at'=>now()]);
        });
    }
}
