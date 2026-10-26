<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['demo.enabled'=>true]);
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


    Illuminate\Support\Facades\Artisan::call('db:seed',['--force'=>true]);
    $cashier=App\Models\User::where('username','cashier')->firstOrFail();
    $manager=App\Models\User::where('username','manager')->firstOrFail();
    $product=App\Models\Products::orderBy('id')->firstOrFail();
    $request=function(array $data,App\Models\User $actor){$r=Illuminate\Http\Request::create('/synthetic','POST',$data);$r->headers->set('Accept','application/json');$r->setUserResolver(fn()=>$actor);return $r;};
    $cart=app(App\Http\Controllers\CartController::class);
    Illuminate\Support\Facades\Auth::setUser($cashier);
    Cart::destroy();
    $cart->index($request(['id'=>$product->id,'name'=>'Forged name','quantity'=>1,'price'=>0],$cashier));
    $item=Cart::content()->first();
    $assert((float)$item->price===(float)$product->price && $item->name===$product->product_name,'Cashier forged catalog price/name accepted');
    $cart->updateCart($request(['qty'=>1,'price'=>0],$cashier),$item->rowId);
    $assert((float)Cart::content()->first()->price===(float)$product->price,'Cashier update price bypass');
    // A stale or tampered session must be re-priced at checkout as well.
    Cart::update($item->rowId,['price'=>0]);
    $before=Illuminate\Support\Facades\DB::table('orders')->count();
    $cart->addTransaction($request(['payment_method'=>'Cash','paid_amount'=>$product->price],$cashier));
    $order=Illuminate\Support\Facades\DB::table('orders')->orderByDesc('id')->first();
    $assert(Illuminate\Support\Facades\DB::table('orders')->count()===$before+1 && (float)$order->total===(float)$product->price,'Checkout did not restore catalog price');
    $assert((int)$order->shop_id===$cashier->shop_id,'Sale escaped assigned shop');
    Illuminate\Support\Facades\Auth::setUser($manager);
    $cart->index($request(['id'=>$product->id,'name'=>'Forged name','quantity'=>1,'price'=>800],$manager));
    $assert((float)Cart::content()->first()->price===800.0,'Authorized manager override failed');
    Cart::destroy();
    $assert(Illuminate\Support\Facades\DB::table('workshop_production')->count()===3,'Synthetic workshop seed incomplete');
    $assert(Illuminate\Support\Facades\DB::table('hub_deliveries')->count()===1,'Synthetic delivery seed incomplete');
    try {Illuminate\Support\Facades\Artisan::call('db:seed',['--force'=>true]);throw new RuntimeException('Repeated seeding was accepted');}
    catch(RuntimeException $e){$assert(str_contains($e->getMessage(),'Refusing to seed'),'Unexpected seed failure');}
    echo "Synthetic migrations/seed, cashier price/name trust boundaries, checkout repricing, manager override, shop binding and reseed refusal passed.\n";
} finally {
    if($server) $server->stop(5);
    Illuminate\Support\Facades\File::deleteDirectory($work);
}
