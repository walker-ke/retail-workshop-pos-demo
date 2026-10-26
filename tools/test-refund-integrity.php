<?php
// Sales/refund integration checks on a disposable local MariaDB server only.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
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
    $shop = App\Models\Shop::firstOrFail();
    $user = App\Models\User::forceCreate(['name'=>'Test cashier','username'=>'fixture-cashier','password'=>Illuminate\Support\Facades\Hash::make(bin2hex(random_bytes(16))),'access_level'=>0,'shop_id'=>$shop->id,'active'=>true]);
    app('auth')->guard()->setUser($user);
    $product = App\Models\Products::forceCreate(['product_name'=>'Test product','product_code'=>'TEST1','brand'=>'Test','price'=>100,'cost_price'=>50,'legacy_quantity'=>0]);
    $db=Illuminate\Support\Facades\DB::class;
    $db::table('product_stock')->insert(['shop_id'=>$shop->id,'product_id'=>$product->id,'quantity'=>98]);
    $orderId=$db::table('orders')->insertGetId(['shop_id'=>$shop->id,'transaction_code'=>123456789,'customer_name'=>'Test customer','customer_id'=>null,'order_date'=>'10/10/26','order_status'=>'Paid','total_products'=>2,'sub_total'=>200,'tax'=>0,'total'=>200,'paid_amount'=>200,'payment_method'=>'Cash','created_at'=>now(),'updated_at'=>now()]);
    $db::table('order_details')->insert(['order_id'=>$orderId,'product_id'=>$product->id,'quantity'=>2,'unit_price'=>100,'total'=>200,'product_name'=>'Test product','product_code'=>'TEST1']);
    $controller = new App\Http\Controllers\RefundController;
    $refund=function($qty) use($controller,$orderId,$product) {return $controller->store(Illuminate\Http\Request::create('/refund-order/'.$orderId,'POST',['refund_qty'=>[$product->id=>$qty]]),$orderId);};
    $refund(1);
    $assert($db::table('orders')->where('id',$orderId)->value('order_status')==='Partially Refunded','Partial status incorrect');
    $assert($db::table('product_stock')->value('quantity')===99,'Partial refund stock incorrect');
    try {$refund(2); throw new RuntimeException('Excess refund accepted');} catch(Illuminate\Validation\ValidationException $e) {}
    $assert($db::table('refunds')->count()===1,'Rejected refund wrote data');
    $refund(1);
    try {$refund(1); throw new RuntimeException('Duplicate refund accepted');} catch(Illuminate\Validation\ValidationException $e) {}
    $assert($db::table('product_stock')->value('quantity')===100,'Refund inflated stock');
    $assert((float)$db::table('refunds')->sum('total_refunded')===200.0,'Refund amount incorrect');
    $user->shop_id=$shop->id+100;
    try {$controller->showOrder($orderId); throw new RuntimeException('Other shop allowed');} catch(Symfony\Component\HttpKernel\Exception\HttpException $e) {$assert($e->getStatusCode()===403,'Wrong authorization response');}
    try {$refund(1); throw new RuntimeException('Other shop refund allowed');} catch(Symfony\Component\HttpKernel\Exception\HttpException $e) {$assert($e->getStatusCode()===403,'Wrong refund authorization response');}
    $user->access_level=9;
    $controller->showOrder($orderId);
    $assert($db::table('refunds')->count()===2,'Authorization failure wrote data');
    $copy=(array)$db::table('orders')->where('id',$orderId)->first();unset($copy['id']);$copy['transaction_code']=123456790;$copy['order_status']='Paid';$copy['total_products']=1;$copy['total']=100;
    $failureOrder=$db::table('orders')->insertGetId($copy);
    $db::table('order_details')->insert(['order_id'=>$failureOrder,'product_id'=>$product->id,'quantity'=>1,'unit_price'=>100,'total'=>100]);
    $db::table('product_stock')->update(['quantity'=>99]);
    $db::unprepared("CREATE TRIGGER fail_refund_line BEFORE INSERT ON refund_details FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Forced integrity-test failure'");
    try {$controller->store(Illuminate\Http\Request::create('/refund-order/'.$failureOrder,'POST',['refund_qty'=>[$product->id=>1]]),$failureOrder);throw new RuntimeException('Forced failure did not run');}catch(Illuminate\Database\QueryException $e){}
    $assert($db::table('refunds')->count()===2,'Failed refund retained header');
    $assert($db::table('product_stock')->value('quantity')===99,'Failed refund changed stock');
    $assert($db::table('orders')->where('id',$failureOrder)->value('order_status')==='Paid','Failed refund changed order');
    $db::unprepared('DROP TRIGGER fail_refund_line');
    $controller->store(Illuminate\Http\Request::create('/refund-order/'.$failureOrder,'POST',['refund_qty'=>[$product->id=>1]]),$failureOrder);
    $assert($db::table('product_stock')->value('quantity')===100,'Retry after rollback failed');
    $user->access_level=1; $user->shop_id=$shop->id;
    $stockController=new App\Http\Controllers\StockInController;
    $addStock=function($extra=[]) use($stockController,$product) {return $stockController->store(Illuminate\Http\Request::create('/add-stock','POST',array_merge(['product_id'=>$product->id,'quantity'=>5],$extra)));};
    $adjust=function($qty) use($stockController,$product) {return $stockController->quickUpdate(Illuminate\Http\Request::create('/quick-stock-update','POST',['product_id'=>$product->id,'quantity'=>$qty]));};
    $addStock(); $adjust(110); $adjust(110);
    $assert($db::table('product_stock')->value('quantity')===110,'Stock operation failed');
    $assert($db::table('stock_ins')->count()===2,'Unchanged stock created history');
    $assert((int)$db::table('stock_ins')->sum('quantity')===10,'Stock adjustment delta incorrect');
    $db::unprepared("CREATE TRIGGER fail_stock_history BEFORE INSERT ON stock_ins FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Forced stock-test failure'");
    foreach ([fn()=> $addStock(), fn()=> $adjust(200)] as $operation) {
        try {$operation();throw new RuntimeException('Stock failure not injected');}catch(Illuminate\Database\QueryException $e){}
        $assert($db::table('product_stock')->value('quantity')===110,'Stock failure was not atomic');
        $assert($db::table('stock_ins')->count()===2,'Failed stock operation retained history');
    }
    $db::unprepared('DROP TRIGGER fail_stock_history');
    try {$addStock(['cost_price'=>-1]);throw new RuntimeException('Negative stock cost accepted');}catch(Illuminate\Validation\ValidationException $e){}
    $user->shop_id=null; $addStock(['shop_id'=>$shop->id]);
    $assert($db::table('product_stock')->value('quantity')===110,'Unassigned manager could select arbitrary shop');
    $user->access_level=9; $user->shop_id=$shop->id;
    $productsController=new App\Http\Controllers\ProductsController;
    $newProduct=['product_name'=>'Created through controller','product_code'=>'CREATE-TEST','brand'=>'Test','price'=>20,'cost_price'=>10,'quantity'=>3,'shop_id'=>$shop->id];
    $productsController->store(Illuminate\Http\Request::create('/add-product','POST',$newProduct));
    $created=App\Models\Products::where('product_code','CREATE-TEST')->firstOrFail();
    $assert($db::table('product_stock')->where('product_id',$created->id)->value('quantity')===3,'Product initial stock incorrect');
    try {$productsController->store(Illuminate\Http\Request::create('/add-product','POST',$newProduct));throw new RuntimeException('Duplicate SKU accepted');}catch(Illuminate\Validation\ValidationException $e){}
    $response=$productsController->deleteProduct(Illuminate\Http\Request::create('/delete-product','DELETE',['delete_product'=>$product->id]));
    $assert(App\Models\Products::whereKey($product->id)->exists(),'Historical product deleted');
    foreach ([fn()=> $productsController->editProduct(999999),fn()=> $productsController->deleteProduct(Illuminate\Http\Request::create('/delete-product','DELETE',['delete_product'=>999999]))] as $operation) {
        try {$operation();throw new RuntimeException('Missing product accepted');}catch(Illuminate\Database\Eloquent\ModelNotFoundException $e){}
    }
    $db::unprepared("CREATE TRIGGER fail_product_stock BEFORE INSERT ON product_stock FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Forced product-stock failure'");
    $failureProduct=$newProduct;$failureProduct['product_code']='FAILED-CREATE';
    try {$productsController->store(Illuminate\Http\Request::create('/add-product','POST',$failureProduct));throw new RuntimeException('Product failure not injected');}catch(Illuminate\Database\QueryException $e){}
    $assert(!App\Models\Products::where('product_code','FAILED-CREATE')->exists(),'Failed initial stock left orphan product');
    $db::unprepared('DROP TRIGGER fail_product_stock');
    $csv=$work.'/products.csv';file_put_contents($csv,"Code,Name,Group,Cost Price,Sale Price,Quantity\nIMPORT-TEST,Imported product,Test,5,10,4\n");
    app()->instance(App\Services\BackupService::class,new class extends App\Services\BackupService {public function create(string $reason='manual'): string {return 'isolated-test-backup';}});
    $upload=new Illuminate\Http\UploadedFile($csv,'products.csv','text/csv',null,true);
    $import=Illuminate\Http\Request::create('/import-products','POST',['shop_id'=>$shop->id],[],['import_file'=>$upload]);
    $productsController->importProducts($import);
    $imported=App\Models\Products::where('product_code','IMPORT-TEST')->firstOrFail();
    $assert($db::table('product_stock')->where('product_id',$imported->id)->value('quantity')===4,'Import stock incorrect');
    view()->share('errors',new Illuminate\Support\ViewErrorBag);
    $request=Illuminate\Http\Request::create('/home','GET');$request->setUserResolver(fn()=> $user);app()->instance('request',$request);
    $screens=[['HomeController','index'],['PosController','index'],['ProductsController','listOfProducts'],['StockInController','overview'],['StockInController','history'],['StockInController','inventoryCountReport'],['ReportController','profitMargin'],['UserController','index'],['ShopController','index'],['PaymentTypeController','index'],['HubController','index'],['TransactionController','index'],['RefundController','index'],['ReceiptSettingsController','index']];
    foreach ($screens as [$class,$method]) {
        $controller=app('App\\Http\\Controllers\\'.$class);$view=$controller->$method($request);$assert(strlen($view->render())>100,'Empty screen '.$class);
    }
    $cart=app(App\Http\Controllers\CartController::class);
    Cart::destroy();Cart::add(['id'=>$product->id,'name'=>'Payment test','qty'=>1,'price'=>100,'options'=>['shop_id'=>$shop->id]]);
    $saleRequest=Illuminate\Http\Request::create('/add-transaction','POST',['payment_method'=>'Card','paid_amount'=>0]);
    $saleRequest->setUserResolver(fn()=> $user);
    $orderCount=$db::table('orders')->count();
    $cart->addTransaction($saleRequest);
    $assert($db::table('orders')->count()===$orderCount,'Underpaid card saved a sale');
    $assert(Cart::count()===1,'Invalid payment cleared the cart');
    $saleRequest->merge(['paid_amount'=>100]);
    $cart->addTransaction($saleRequest);
    $assert($db::table('orders')->count()===$orderCount+1,'Valid card payment did not save');
    $assert(Cart::count()===0,'Successful payment did not clear cart');
    $last=$db::table('orders')->orderByDesc('id')->first();
    $assert((float)$last->paid_amount===100.0 && $last->order_status==='Paid','Card amount/status incorrect');
    Cart::add(['id'=>$product->id,'name'=>'Wrong shop','qty'=>1,'price'=>100,'options'=>['shop_id'=>$shop->id+1]]);
    $cart->addTransaction($saleRequest);
    $assert($db::table('orders')->count()===$orderCount+1 && Cart::count()===0,'Wrong-shop cart was sold');
    App\Models\PaymentType::create(['name'=>'Paybill','enabled'=>true,'change_allowed'=>false,'mark_transaction_as_paid'=>true]);
    $runSale=function($method,$paid,$parts=[]) use($user,$product,$shop,$cart) {
        Cart::destroy();
        $add=Illuminate\Http\Request::create('/add-cart','POST',['id'=>$product->id,'name'=>'Checkout test','quantity'=>1,'price'=>100]);
        $cart->index($add);
        $assertShop=Cart::content()->first()->options->shop_id;
        if ((int)$assertShop !== (int)$shop->id) throw new RuntimeException('Cart did not stamp its shop');
        $request=Illuminate\Http\Request::create('/add-transaction','POST',array_merge(['payment_method'=>$method,'paid_amount'=>$paid],$parts));
        $request->setUserResolver(fn()=> $user);$cart->addTransaction($request);
        return Illuminate\Support\Facades\DB::table('orders')->orderByDesc('id')->first();
    };
    $cashOrder=$runSale('Cash',120);
    $assert((float)$cashOrder->paid_amount===120.0 && Cart::count()===0,'Cash tender failed');
    $receipt=App\Support\ReceiptData::order($cashOrder,collect(),$shop);$assert($receipt['change']===20.0,'Cash change incorrect');
    $combinedOrder=$runSale('__combined',null,['cash_amount'=>40,'paybill_amount'=>60]);
    $parts=json_decode($combinedOrder->payment_breakdown,true);
    $assert($combinedOrder->payment_method==='Combined payment' && (float)$combinedOrder->paid_amount===100.0 && count($parts)===2,'Combined payment incorrect');
    $count=$db::table('orders')->count();$runSale('__combined',null,['cash_amount'=>40,'paybill_amount'=>50]);
    $assert($db::table('orders')->count()===$count && Cart::count()===1,'Invalid split saved or cleared cart');
    $runSale('Disabled method',100);
    $assert($db::table('orders')->count()===$count && Cart::count()===1,'Disabled payment saved or cleared cart');
    Cart::destroy();
    $otherShop=App\Models\Shop::create(['name'=>'Second test shop','is_active'=>true]);
    $switch=function($shopId) use($user) {
        $request=Illuminate\Http\Request::create('/switch-shop','POST',['shop_id'=>$shopId]);
        $request->setUserResolver(fn()=> $user);app()->instance('request',$request);
        $route=app('router')->getRoutes()->match($request);return $route->run();
    };
    Cart::add(['id'=>$product->id,'name'=>'Switch test','qty'=>1,'price'=>100,'options'=>['shop_id'=>$shop->id]]);
    $switch($otherShop->id);
    $assert($user->activeShopId()===$otherShop->id && Cart::count()===0,'Owner shop switch did not clear old cart');
    Cart::add(['id'=>$product->id,'name'=>'Same shop','qty'=>1,'price'=>100,'options'=>['shop_id'=>$otherShop->id]]);
    $switch($otherShop->id);$assert(Cart::count()===1,'Selecting unchanged shop cleared cart');
    $otherShop->update(['is_active'=>false]);
    try {$switch($otherShop->id);throw new RuntimeException('Inactive shop selected');}catch(Illuminate\Validation\ValidationException $e){}
    $switch(null);$assert($user->activeShopId()===null && Cart::count()===0,'All-shops selection failed');
    echo "PASS: payment rejection/success, cart preservation, cross-shop cart rejection and owner shop switching.\n";
    Cart::destroy();
    echo "PASS: product creation/import, duplicate SKU validation, missing records and ".count($screens)." rendered screens.\n";
    echo "PASS: stock adjustments, audit deltas, validation, shop restrictions and failure rollback.\n";
    echo "PASS: partial/full refunds, duplicate/excess rejection, stock/money totals shop authorization and failure rollback. Live data untouched.\n";
} finally {
    Illuminate\Support\Facades\DB::disconnect('mysql');
    if($server)$server->stop(5);
    Illuminate\Support\Facades\File::deleteDirectory($work);
}
