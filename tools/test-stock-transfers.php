<?php
// Sales/refund integration checks on a disposable local MariaDB server only.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (($argv[1] ?? '') === '--worker') {
    config(['database.default'=>'mysql','database.connections.mysql.host'=>'localhost','database.connections.mysql.unix_socket'=>$argv[2],'database.connections.mysql.database'=>'rehearsal','database.connections.mysql.username'=>'root','database.connections.mysql.password'=>'','session.driver'=>'array']);
    Illuminate\Support\Facades\DB::purge('mysql');
    try {
        $actor=App\Models\User::findOrFail((int)$argv[5]);
        app(App\Services\StockTransferService::class)->change((int)$argv[3],$argv[4],$actor,isset($argv[6])?(int)$argv[6]:null);
        echo 'OK'; exit(0);
    } catch(Illuminate\Validation\ValidationException $e) {echo 'CONFLICT';exit(0);}
    catch(Throwable $e){fwrite(STDERR,$e->getMessage());exit(1);}
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

    $db = Illuminate\Support\Facades\DB::class;
    $source = App\Models\Shop::firstOrFail();
    $dest = App\Models\Shop::create(['name'=>'Receiving Shop','is_active'=>true]);
    $third = App\Models\Shop::create(['name'=>'Other Shop','is_active'=>true]);
    $makeUser = fn($name,$shop) => App\Models\User::forceCreate(['name'=>$name,'username'=>strtolower(str_replace(' ','-',$name)),'password'=>Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(16))),'access_level'=>0,'shop_id'=>$shop,'active'=>true]);
    $sender = $makeUser('Source Cashier',$source->id); $receiver = $makeUser('Receiving Cashier',$dest->id); $outsider = $makeUser('Other Cashier',$third->id);
    $product=App\Models\Products::forceCreate(['product_name'=>'Transfer test shirt','product_code'=>'TRANSFER-TEST','brand'=>'Test','price'=>100,'cost_price'=>50,'legacy_quantity'=>0]);
    $db::table('product_stock')->insert(['shop_id'=>$source->id,'product_id'=>$product->id,'quantity'=>10]);
    $service=app(App\Services\StockTransferService::class);$controller=app(App\Http\Controllers\StockTransferController::class);
    $read = fn($shop) => $db::table('product_stock')->where('shop_id',$shop)->where('product_id',$product->id)->first();
    $request = function($actor,$data) {app('auth')->guard()->setUser($actor);$r=Illuminate\Http\Request::create('/stock-requests','POST',$data);$r->setUserResolver(fn()=>$actor);return $r;};
    $key=(string)Illuminate\Support\Str::uuid();
    $data=['request_key'=>$key,'from_shop_id'=>$source->id,'product_id'=>$product->id,'quantity'=>8,'note'=>'Test transfer'];
    $controller->store($request($receiver,$data),$service); $id=(int)$db::table('stock_transfers')->value('id');
    $controller->store($request($receiver,$data),$service);
    $assert($db::table('stock_transfers')->count()===1 && $db::table('stock_transfer_events')->count()===1,'Duplicate request was not idempotent');
    $reject=function(callable $action) {try{$action();}catch(Illuminate\Validation\ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e){return;}throw new RuntimeException('Invalid transfer action succeeded');};
    $reject(fn()=> $service->change($id,'accept',$outsider,5));
    $reject(fn()=> $service->change($id,'accept',$receiver,5));
    $reject(fn()=> $service->change($id,'receive',$receiver));
    $reject(fn()=> $service->change($id,'accept',$sender,11));
    $reject(fn()=> $service->change($id,'accept',$sender,-1));
    $service->change($id,'accept',$sender,5);
    $assert((int)$read($source->id)->quantity===10 && (int)$read($source->id)->reserved_quantity===5,'Acceptance reservation wrong');
    $reject(fn()=> $service->change($id,'accept',$sender,5));
    // Absolute stock corrections and zero-stock sales must not consume a reservation.
    $sender->access_level=1; app('auth')->guard()->setUser($sender);
    $reject(fn()=>app(App\Http\Controllers\StockInController::class)->quickUpdate($request($sender,['product_id'=>$product->id,'quantity'=>4])));
    $sender->access_level=0;
    App\Models\PaymentType::firstOrCreate(['name'=>'Cash'],['enabled'=>true,'position'=>0,'change_allowed'=>true,'customer_required'=>false,'mark_transaction_as_paid'=>true]);
    Cart::destroy(); Cart::add(['id'=>$product->id,'name'=>$product->product_name,'qty'=>6,'price'=>100,'options'=>['shop_id'=>$source->id]]);
    $reject(fn()=>app(App\Http\Controllers\CartController::class)->addTransaction($request($sender,['payment_method'=>'Cash','paid_amount'=>600])));
    $assert($db::table('orders')->count()===0 && (int)$read($source->id)->quantity===10,'Rejected sale changed stock/orders'); Cart::destroy();
    $service->change($id,'dispatch',$sender);
    $assert((int)$read($source->id)->quantity===5 && (int)$read($source->id)->reserved_quantity===0 && (int)$read($dest->id)->quantity===0,'Dispatch balances wrong');
    $reject(fn()=> $service->change($id,'dispatch',$sender)); $reject(fn()=> $service->change($id,'cancel',$receiver)); $reject(fn()=> $service->change($id,'receive',$sender));
    $service->change($id,'receive',$receiver); $reject(fn()=> $service->change($id,'receive',$receiver));
    $assert((int)$read($dest->id)->quantity===5 && (int)$read($source->id)->quantity===5,'Receipt did not conserve total stock');
    $assert($db::table('stock_ins')->where('reference','Transfer #'.$id)->sum('quantity')==0,'Stock audit deltas wrong');
    $assert($db::table('stock_transfer_events')->where('stock_transfer_id',$id)->count()===4,'Transition audit missing');
    // Cancellation releases reserves, and competing accepts cannot over-reserve.
    $new=function($qty)use($db,$source,$dest,$product){return $db::table('stock_transfers')->insertGetId(['request_key'=>(string)Illuminate\Support\Str::uuid(),'product_id'=>$product->id,'from_shop_id'=>$source->id,'to_shop_id'=>$dest->id,'requested_quantity'=>$qty,'status'=>'requested','created_at'=>now(),'updated_at'=>now()]);};
    $a=$new(4);$b=$new(4);$service->change($a,'accept',$sender,4);$reject(fn()=> $service->change($b,'accept',$sender,4));
    $service->change($a,'cancel',$receiver);$assert((int)$read($source->id)->reserved_quantity===0,'Cancellation failed to release reservation');
    $service->change($b,'accept',$sender,4);$service->change($b,'cancel',$receiver);
    $c=$new(1);$service->change($c,'decline',$sender);$reject(fn()=> $service->change($c,'accept',$sender,1));
    // Force an audit write error: all stock and state changes must roll back.
    $d=$new(2);$service->change($d,'accept',$sender,2);
    $db::unprepared("CREATE TRIGGER reject_transfer_audit BEFORE INSERT ON stock_transfer_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test rollback'");
    try{$service->change($d,'dispatch',$sender);throw new RuntimeException('Audit failure ignored');}catch(Illuminate\Database\QueryException $e){}
    $db::unprepared('DROP TRIGGER reject_transfer_audit');
    $assert((int)$read($source->id)->quantity===5 && (int)$read($source->id)->reserved_quantity===2 && $db::table('stock_transfers')->where('id',$d)->value('status')==='accepted','Dispatch failure did not roll back');
    $service->change($d,'cancel',$receiver);

    $race=function(array $ids,string $action,$actor,?int $qty=null)use($socket,$assert){
        $children=[];
        foreach($ids as $transferId){$args=[PHP_BINARY,__FILE__,'--worker',$socket,(string)$transferId,$action,(string)$actor->id];if($qty!==null)$args[]=(string)$qty;$child=new Symfony\Component\Process\Process($args);$child->setTimeout(30);$child->start();$children[]=$child;}
        $success=0;foreach($children as $child){$child->wait();$assert($child->isSuccessful(),'Worker failed: '.$child->getErrorOutput());if($child->getOutput()==='OK')$success++;}
        $assert($success===1,'Concurrent '.$action.' must succeed exactly once');
    };
    $e=$new(4);$f=$new(4);$race([$e,$f,$e,$f,$e,$f],'accept',$sender,4);
    $chosen=(int)$db::table('stock_transfers')->whereIn('id',[$e,$f])->where('status','accepted')->value('id');
    $assert((int)$read($source->id)->reserved_quantity===4,'Concurrent accepts over-reserved');
    $race(array_fill(0,6,$chosen),'dispatch',$sender);
    $race(array_fill(0,6,$chosen),'receive',$receiver);
    $assert((int)$read($source->id)->quantity===1 && (int)$read($dest->id)->quantity===9,'Concurrent transfer duplicated or lost stock');
    echo "PASS: concurrent competing accepts, six simultaneous dispatches and six receipts update stock exactly once.\n";

    // Simple workflow: a source confirmation replaces separate accept/dispatch clicks.
    $db::table('product_stock')->where('product_id',$product->id)->where('shop_id',$source->id)->update(['quantity'=>20,'reserved_quantity'=>0]);
    $db::table('product_stock')->where('product_id',$product->id)->where('shop_id',$dest->id)->update(['quantity'=>6,'reserved_quantity'=>0]);
    $before=$db::table('stock_transfers')->count();$lowKey=(string)Illuminate\Support\Str::uuid();
    $lowData=['request_key'=>$lowKey,'from_shop_id'=>$source->id,'product_id'=>$product->id,'quantity'=>10];
    $reject(fn()=> $controller->store($request($receiver,$lowData),$service));
    $assert($db::table('stock_transfers')->count()===$before,'Stock above 5 was allowed to request');
    $db::table('product_stock')->where('product_id',$product->id)->where('shop_id',$dest->id)->update(['quantity'=>5]);
    $controller->store($request($receiver,$lowData),$service);$simple=(int)$db::table('stock_transfers')->where('request_key',$lowKey)->value('id');
    $reject(fn()=> $service->change($simple,'approve',$receiver,10));
    $race(array_fill(0,6,$simple),'approve',$sender,10);
    $assert((int)$read($source->id)->quantity===10 && (int)$read($source->id)->reserved_quantity===0 && (int)$read($dest->id)->quantity===5,'Simple source confirmation balances wrong');
    $race(array_fill(0,6,$simple),'receive',$receiver);
    $assert((int)$read($dest->id)->quantity===15,'Simple receipt balances wrong');
    // Replay remains safe even after stock increases, but a new request is rejected.
    $controller->store($request($receiver,$lowData),$service);
    $lowData['request_key']=(string)Illuminate\Support\Str::uuid();
    $reject(fn()=> $controller->store($request($receiver,$lowData),$service));
    $assert($db::table('stock_transfer_events')->where('stock_transfer_id',$simple)->count()===3,'Simple workflow should record request, approval and receipt only');
    echo "PASS: stock 6 blocks requests; stock 5 allows requests; six simultaneous confirmations/receipts act once; high-stock replays cannot create new requests.\n";

    $db::table('product_stock')->where('product_id',$product->id)->where('shop_id',$source->id)->update(['quantity'=>6]);
    $db::table('product_stock')->where('product_id',$product->id)->where('shop_id',$dest->id)->update(['quantity'=>0]);
    $partial=$new(6);$service->change($partial,'approve',$sender,4);$service->change($partial,'receive',$receiver);
    $assert((int)$read($dest->id)->quantity===4 && (int)$read($source->id)->quantity===2,'Partial confirmation must receive only confirmed quantity');
    // A synthetic arriving request remains available to show the Receive button.
    $visual=$new(1);$service->change($visual,'accept',$sender,1);$service->change($visual,'dispatch',$sender);
    app('auth')->guard()->setUser($receiver);
    session()->forget('success');
    view()->share('errors', new Illuminate\Support\ViewErrorBag());
    $html=$controller->index($request($receiver,[]))->render();$assert(str_contains($html,'Shop Stock Requests') && !str_contains($html,'Who handled this transfer?'),'Transfer page still shows detailed activity');
    file_put_contents($work.'/preview.html',$html);
    $alerts=$controller->alerts($request($sender,[]))->getData(true);$assert(isset($alerts['count']),'Alerts failed');
    echo "PASS: request replay, shop permissions, partial acceptance, stock reservation, blocked reserved sales/counts, dispatch/receipt, duplicate rejection, cancellation, competing acceptance, audit rollback and page rendering.\n";
} finally {
    Illuminate\Support\Facades\DB::disconnect('mysql');
    if($server)$server->stop(5);
    Illuminate\Support\Facades\File::deleteDirectory($work);
}
