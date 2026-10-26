<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(($argv[1]??'')==='--worker'){
 config(['database.connections.mysql.host'=>'localhost','database.connections.mysql.unix_socket'=>$argv[2],'database.connections.mysql.database'=>'rehearsal','database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'']);Illuminate\Support\Facades\DB::purge('mysql');
 $service=app(App\Services\HubStockService::class);$actor=App\Models\User::findOrFail((int)$argv[4]);
 try{
  if($argv[3]==='send')$service->send($actor,(int)$argv[5],(int)$argv[6],(int)$argv[7],(int)$argv[8],$argv[9],null);
  else $service->receive($actor,(int)$argv[5],(int)$argv[6],$argv[7],null);
  echo 'OK';exit;
 }catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){echo 'REJECTED';exit;}
}
$originalBase = base_path();
$work = sys_get_temp_dir().'/bb-rehearsal-'.bin2hex(random_bytes(6));
mkdir($work, 0700);
$server = null;
$run = function (array $args) {
    $process = new Symfony\Component\Process\Process($args);
    $process->setTimeout(60); $process->mustRun(); return $process->getOutput();
};
$assert = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
try {
    $run(['mariadb-install-db', '--no-defaults', '--datadir='.$work.'/db', '--auth-root-authentication-method=normal', '--skip-test-db']);
    $socket = $work.'/mysql.sock';
    $server = new Symfony\Component\Process\Process(['mariadbd', '--no-defaults', '--datadir='.$work.'/db', '--socket='.$socket, '--pid-file='.$work.'/pid', '--skip-networking', '--skip-log-bin', '--log-error='.$work.'/server.log']);
    $server->setTimeout(null); $server->start();
    $client = ['mysql', '--no-defaults', '--socket='.$socket, '--user=root'];
    $ready = false;
    for ($i=0; $i<300; $i++) {
        if (!$server->isRunning()) throw new RuntimeException('Rehearsal server failed');
        if (file_exists($socket)) {
            try { $run(array_merge($client, ['-e', 'SELECT 1'])); $ready = true; break; } catch (Throwable $e) {}
        }
        usleep(100000);
    }
    $assert($ready, 'Rehearsal server timed out');
    $run(array_merge($client, ['-e', 'CREATE DATABASE rehearsal']));
    config(['database.default'=>'mysql', 'database.connections.mysql.host'=>'localhost', 'database.connections.mysql.unix_socket'=>$socket, 'database.connections.mysql.database'=>'rehearsal', 'database.connections.mysql.username'=>'root', 'database.connections.mysql.password'=>'', 'session.driver'=>'array']);
    Illuminate\Support\Facades\DB::purge('mysql');
    // Test migrations must never update real backup status or upload test archives.
    config(['backup.directory'=>$work.'/backups','backup.key_path'=>$work.'/backup-key','backup.uploads'=>[], 'backup.external_path'=>null,'backup.google.settings_path'=>$work.'/google-drive.json']);
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force'=>true]);

    $db=Illuminate\Support\Facades\DB::class;$service=app(App\Services\HubStockService::class);
    $hub=(int)$db::table('hubs')->where('name','NORTH DEMO HUB')->value('id');$otherHub=(int)$db::table('hubs')->where('name','SOUTH DEMO HUB')->value('id');
    $assert($hub && $otherHub && $db::table('hubs')->count()===2,'Uppercase hub seeds missing');
    $shop=App\Models\Shop::firstOrFail();$otherShop=App\Models\Shop::create(['name'=>'Other shop','is_active'=>true]);
    $hash=Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(12)));
    $makeUser=fn($name,$role,$shopId)=>App\Models\User::forceCreate(['name'=>$name,'username'=>str_replace(' ','-',$name),'access_level'=>$role,'shop_id'=>$shopId,'active'=>true,'password'=>$hash]);
    $owner=$makeUser('Hub Owner',9,$shop->id);$cashier=$makeUser('Shop Cashier',0,$shop->id);$manager=$makeUser('Shop Manager',1,$shop->id);$outsider=$makeUser('Other Cashier',0,$otherShop->id);
    $product=App\Models\Products::forceCreate(['product_name'=>'206 Backle shoes test','product_code'=>'HUB-TEST','brand'=>'Shoes','price'=>100,'cost_price'=>50,'legacy_quantity'=>0]);
    $db::table('product_stock')->insert(['shop_id'=>$shop->id,'product_id'=>$product->id,'quantity'=>50,'reserved_quantity'=>2]);
    $uuid=fn()=>(string)Illuminate\Support\Str::uuid();
    $reject=function(callable $operation){try{$operation();}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){return;}throw new RuntimeException('Invalid hub operation succeeded');};
    $readHub=fn()=>(int)$db::table('hub_stock')->where('hub_id',$hub)->where('product_id',$product->id)->value('quantity');
    $readShop=fn()=>(int)$db::table('product_stock')->where('shop_id',$shop->id)->where('product_id',$product->id)->value('quantity');
    $key=$uuid();$service->stock($owner,$hub,$product->id,100,'add',$key,'Initial delivery');$service->stock($owner,$hub,$product->id,100,'add',$key,'Initial delivery');
    $assert($readHub()===100 && $db::table('hub_stock_events')->count()===1,'Hub addition replay changed stock');
    $reject(fn()=> $service->stock($owner,$hub,$product->id,101,'add',$key,'Initial delivery'));
    foreach([$cashier] as $actor){$reject(fn()=> $service->stock($actor,$hub,$product->id,10,'add',$uuid(),'Forged'));$reject(fn()=> $service->send($actor,$hub,$shop->id,$product->id,10,$uuid(),null));$reject(fn()=> $service->archive($actor,$hub));}
    try{$db::table('hub_stock')->where('hub_id',$hub)->where('product_id',$product->id)->update(['quantity'=>-1]);throw new RuntimeException('Negative hub stock accepted');}catch(Illuminate\Database\QueryException $e){}
    $assert($readHub()===100,'Database constraint changed stock');
    $sendKey=$uuid();$id=$service->send($owner,$hub,$shop->id,$product->id,20,$sendKey,'For display');$again=$service->send($owner,$hub,$shop->id,$product->id,20,$sendKey,'For display');
    $assert($id===$again && $readHub()===80 && $readShop()===50,'Sending must debit hub only, once, even when shop has >5');
    $reject(fn()=> $service->send($owner,$hub,$shop->id,$product->id,81,$uuid(),null));
    $reject(fn()=> $service->send($owner,$hub,$shop->id,$product->id,19,$sendKey,'For display'));
    $reject(fn()=> $service->archive($owner,$hub));
    app('auth')->guard()->setUser($owner);
    $reject(fn()=> app(App\Http\Controllers\ShopController::class)->update(Illuminate\Http\Request::create('/update-shop/'.$shop->id,'POST',['name'=>$shop->name]),$shop->id));
    $assert((bool)$db::table('shops')->where('id',$shop->id)->value('is_active'),'Shop with incoming hub delivery was deactivated');
    $reject(fn()=> $service->receive($owner,$id,20,$uuid(),null));$reject(fn()=> $service->receive($outsider,$id,20,$uuid(),null));
    $reject(fn()=> $service->receive($cashier,$id,21,$uuid(),null));$reject(fn()=> $service->receive($cashier,$id,18,$uuid(),null));
    $receiveKey=$uuid();$service->receive($cashier,$id,18,$receiveKey,'2 missing');$service->receive($cashier,$id,18,$receiveKey,'2 missing');
    $assert((int)$db::table('product_stock')->where('shop_id',$shop->id)->where('product_id',$product->id)->value('reserved_quantity')===2,'Hub receipt altered shop reservations');
    $assert($readShop()===68 && $readHub()===80 && $db::table('hub_deliveries')->where('id',$id)->value('status')==='reported','Short delivery did not credit exact quantity once');
    $reject(fn()=> $service->receive($cashier,$id,20,$receiveKey,'2 missing'));$reject(fn()=> $service->receive($cashier,$id,18,$uuid(),'2 missing'));
    $reject(fn()=> $service->resolve($cashier,$id,'returned',$uuid(),'Forged'));
    $resolveKey=$uuid();$service->resolve($owner,$id,'returned',$resolveKey,'Two pairs physically returned');$service->resolve($owner,$id,'returned',$resolveKey,'Two pairs physically returned');
    $assert($readHub()===82 && $readShop()===68,'Return-to-hub did not restore only remainder once');
    $reject(fn()=> $service->resolve($owner,$id,'written_off',$resolveKey,'Changed'));
    $id2=$service->send($owner,$hub,$shop->id,$product->id,10,$uuid(),null);$service->receive($manager,$id2,7,$uuid(),'3 damaged');
    $reject(fn()=> $service->resolve($owner,$id2,'written_off',$uuid(),''));$service->resolve($owner,$id2,'written_off',$uuid(),'Three pairs damaged and disposed');
    $assert($readHub()===72 && $readShop()===75,'Write-off changed hub or incorrect received quantity');
    $countKey=$uuid();$service->stock($owner,$hub,$product->id,5,'count',$countKey,'Physical count');$service->stock($owner,$hub,$product->id,5,'count',$countKey,'Physical count');
    $assert($readHub()===5 && (int)$db::table('hub_stock_events')->where('hub_id',$hub)->sum('delta')===5,'Hub audit ledger does not reconcile');
    $db::unprepared("CREATE TRIGGER fail_hub_audit BEFORE INSERT ON hub_stock_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected hub audit failure'");
    try{$service->send($owner,$hub,$shop->id,$product->id,2,$uuid(),null);throw new RuntimeException('Audit failure did not happen');}catch(Illuminate\Database\QueryException $e){}
    $assert($readHub()===5 && $db::table('hub_deliveries')->count()===2,'Failed dispatch left balance or delivery behind');$db::unprepared('DROP TRIGGER fail_hub_audit');
    echo "PASS: owner permissions, hub/shop separation, stock replay, dispatch replay, partial receipt, return, write-off, audit reconciliation and failure rollback.\n";
    // Parallel requests use independent processes and real MariaDB row locks.
    $runWorkers=function(array $commands){$processes=[];foreach($commands as $args){$p=new Symfony\Component\Process\Process(array_merge(['php',__FILE__,'--worker'],$args));$p->setTimeout(45);$p->start();$processes[]=$p;}$outputs=[];foreach($processes as $p){$p->wait();if(!$p->isSuccessful())throw new RuntimeException($p->getErrorOutput().$p->getOutput());$outputs[]=trim($p->getOutput());}return $outputs;};
    $raceKey=$uuid();$commands=array_fill(0,6,[$socket,'send',$owner->id,$hub,$shop->id,$product->id,5,$raceKey]);$outputs=$runWorkers($commands);
    $assert(count(array_filter($outputs,fn($v)=>$v==='OK'))===6 && $readHub()===0 && $db::table('hub_deliveries')->count()===3,'Concurrent replay duplicated hub debit');
    $raceId=(int)$db::table('hub_deliveries')->where('request_key',$raceKey)->value('id');$rkey=$uuid();$commands=array_fill(0,6,[$socket,'receive',$cashier->id,$raceId,5,$rkey]);$outputs=$runWorkers($commands);
    $assert($readShop()===80 && count(array_filter($outputs,fn($v)=>$v==='OK'))===6,'Concurrent receipt credited more than once');
    $service->stock($owner,$hub,$product->id,5,'add',$uuid(),'Competing sends fixture');$commands=[];for($i=0;$i<2;$i++)$commands[]=[$socket,'send',$owner->id,$hub,$shop->id,$product->id,5,$uuid()];$outputs=$runWorkers($commands);
    $assert(count(array_filter($outputs,fn($v)=>$v==='OK'))===1 && $readHub()===0,'Competing sends over-dispatched hub stock');
    $reject(fn()=> $service->archive($owner,$hub));
    $pending=$db::table('hub_deliveries')->where('status','dispatched')->first();$service->receive($cashier,$pending->id,5,$uuid(),null);
    $service->archive($owner,$hub);$service->archive($owner,$hub);
    $assert(!(bool)$db::table('hubs')->where('id',$hub)->value('is_active') && $db::table('hub_deliveries')->count()===4,'Archive removed history or failed');
    $reject(fn()=> $service->stock($owner,$hub,$product->id,1,'add',$uuid(),'Invalid'));
    echo "PASS: six concurrent repeated dispatches and receipts act once; competing sends cannot overdraw; archive preserves history and blocks pending deliveries.\n";
    // Page rendering and input normalization on the same isolated database.
    app('auth')->guard()->setUser($owner);view()->share('errors',new Illuminate\Support\ViewErrorBag);
    $request=Illuminate\Http\Request::create('/hubs','GET');$request->setUserResolver(fn()=>$owner);app()->instance('request',$request);
    $controller=app(App\Http\Controllers\HubController::class);$html=$controller->index($request)->render();$assert(str_contains($html,'SOUTH DEMO HUB') && !str_contains($html,'hubCatalog') && str_contains($html,route('hubs.stock-form',$otherHub)) && str_contains($html,route('hubs.send-form',$otherHub)),'Hub page failed to render');
    foreach(['stockForm'=>'stock','sendForm'=>'send'] as $method=>$mode){
        $formHtml=$controller->$method($request,$otherHub)->render();
        $assert(str_contains($formHtml,'hubCatalog') && str_contains($formHtml,'action="'.route('hubs.'.$mode,$otherHub).'"') && !str_contains($formHtml,'action="'.route('hubs.'.($mode==='stock'?'send':'stock'),$otherHub).'"'),'Hub forms were not separated');
    }
    $availability=$controller->productStock($otherHub,$product->id)->getData(true);
    $assert(array_key_exists('hub_quantity',$availability) && count($availability['shops'])>0,'Product shop stock missing');
    $assert($availability['hub_quantity']===(int)($db::table('hub_stock')->where('hub_id',$otherHub)->where('product_id',$product->id)->value('quantity')??0),'Hub quantity incorrect');
    $assert(count($availability['shops'])===$db::table('shops')->where('is_active',true)->count(),'Active shops omitted');
    foreach($availability['shops'] as $row){
        $shopId=$db::table('shops')->where('name',$row['name'])->value('id');
        $stored=$db::table('product_stock')->where('shop_id',$shopId)->where('product_id',$product->id)->first();
        $assert($row['quantity']===(int)($stored->quantity??0) && $row['reserved']===(int)($stored->reserved_quantity??0) && $row['available']===max(0,$row['quantity']-$row['reserved']),'Shop quantities or reservation handling incorrect');
    }
    $stockRequest=Illuminate\Http\Request::create('/hubs/'.$otherHub.'/stock','POST',['product_id'=>$product->id,'quantity'=>2,'action'=>'add','request_key'=>$uuid()]);$stockRequest->setUserResolver(fn()=>$owner);
    $controller->stock($stockRequest,$otherHub,$service);
    $assert($db::table('hub_stock_events')->latest('id')->value('note')===null,'Hub add still requires a reference');
    $beforeCount=(int)$db::table('hub_stock')->where('hub_id',$otherHub)->where('product_id',$product->id)->value('quantity');
    $reject(fn()=> $service->stock($owner,$otherHub,$product->id,99,'count',$uuid(),null,$beforeCount+1));
    $assert((int)$db::table('hub_stock')->where('hub_id',$otherHub)->where('product_id',$product->id)->value('quantity')===$beforeCount,'Stale count overwrote current stock');
    $service->stock($owner,$otherHub,$product->id,$beforeCount,'count',$uuid(),null,$beforeCount);
    $save=Illuminate\Http\Request::create('/hubs','POST',['name'=>'  extra   hub ']);$save->setUserResolver(fn()=>$owner);$controller->save($save);$assert($db::table('hubs')->where('name','EXTRA HUB')->exists(),'Hub name not normalized');
    $service->stock($owner,$otherHub,$product->id,20,'add',$uuid(),'Preview fixture');
    $service->send($owner,$otherHub,$shop->id,$product->id,20,$uuid(),'Count before confirming');
    $request=Illuminate\Http\Request::create('/stock-requests','GET');$request->setUserResolver(fn()=>$cashier);app('auth')->guard()->setUser($cashier);app()->instance('request',$request);
    $html=app(App\Http\Controllers\StockTransferController::class)->index($request)->render();$assert(str_contains($html,'Deliveries from hubs') && !str_contains($html,'href="'.route('hubs.index').'"'),'Cashier hub delivery page/navigation failed');
    file_put_contents($work.'/preview.html',$html);
    echo "PASS: hubs and cashier deliveries render, uppercase names, private owner menu. Local/live business data untouched.\n";
} finally {if($server)$server->stop();Illuminate\Support\Facades\File::deleteDirectory($work);}
