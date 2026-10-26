<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Request;
use App\Http\Controllers\CartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\PaymentTypeController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', function () {
    return view('welcome');
});

Route::middleware('throttle:authentication')->group(function () {
    Auth::routes(['register' => false, 'reset' => false]);
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home')->middleware('access:dashboard');

//SHOP SWITCHER (top admin only; stores the chosen "acting shop" in the session)
Route::post('/switch-shop', function (\Illuminate\Http\Request $request) {
    if (!auth()->user()->isTopAdmin()) {
        abort(403);
    }
    $data = $request->validate(['shop_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('shops', 'id')->where('is_active', true)]]);
    $shopId = isset($data['shop_id']) ? (int) $data['shop_id'] : null;
    $changed = auth()->user()->activeShopId() !== $shopId;
    $hadItems = \Cart::count() > 0;
    if ($changed) \Cart::destroy();
    session(['current_shop_id' => $shopId]);
    if ($changed && $hadItems) return redirect()->back()->with('success', 'Shop changed. The previous shop cart was cleared.');
    return redirect()->back();
})->name('switch_shop')->middleware('auth')->block(30, 30);

//USER TAB
Route::get('/user-list', [UserController::class, 'index'])->name('user')->middleware('access:users_security');
Route::post('/add-user', [UserController::class, 'store'])->name('add_user');
Route::post('/update-user/{id}', [UserController::class, 'update'])->name('update_user');
Route::post('/reset-password/{id}', [UserController::class, 'resetPassword'])->name('reset_password');
Route::delete('/delete-user', [UserController::class, 'deleteUser']);
Route::get('/user-security', [UserController::class, 'security'])->name('user_security')->middleware('access:users_security');

//PRODUCT TAB
Route::middleware('access:products')->group(function () {
    Route::post('/add-product', [ProductsController::class, 'store'])->name('add_product');//ADD PRODUCT
    Route::post('/add-brand', [ProductsController::class, 'addBrand']);//ADD BRAND
    Route::put('/edit-brand', [ProductsController::class, 'editBrand']);//EDIT BRAND
    Route::delete('/delete-brand',[ProductsController::class, 'deleteBrand']);//DELETE BRAND
    Route::get('/export-products', [ProductsController::class, 'exportProducts'])->name('export_products');//EXPORT PRODUCTS CSV
    Route::get('/import-products/template', [ProductsController::class, 'importTemplate'])->name('import_products_template');//DOWNLOAD IMPORT TEMPLATE
    Route::post('/import-products', [ProductsController::class, 'importProducts'])->name('import_products');//IMPORT PRODUCTS CSV
    Route::get('/edit-product/{id}', [ProductsController::class, 'editProduct']);//EDIT PRODUCT
    Route::delete('/delete-product', [ProductsController::class, 'deleteProduct']);//DELETE PRODUCT
    Route::post('/update-product/{id}', [ProductsController::class, 'updateProduct']);//UPDATE A PRODUCT
});


// Hubs replace the supplier screens; historical supplier records remain intact.
Route::middleware(['auth'])->group(function () {
    Route::get('/hubs', [\App\Http\Controllers\HubController::class, 'index'])->name('hubs.index');
    Route::get('/hubs/stock', [\App\Http\Controllers\HubController::class, 'stockForm'])->name('hubs.stock-page');
    Route::get('/hubs/send', [\App\Http\Controllers\HubController::class, 'sendForm'])->name('hubs.send-page');
    Route::post('/hubs', [\App\Http\Controllers\HubController::class, 'save'])->name('hubs.create');
    Route::post('/hubs/{id}/rename', [\App\Http\Controllers\HubController::class, 'save'])->whereNumber('id')->name('hubs.rename');
    Route::get('/hubs/{id}/stock', [\App\Http\Controllers\HubController::class, 'stockForm'])->whereNumber('id')->name('hubs.stock-form');
    Route::get('/hubs/{id}/send', [\App\Http\Controllers\HubController::class, 'sendForm'])->whereNumber('id')->name('hubs.send-form');
    Route::get('/hubs/{id}/products/{product}/stock', [\App\Http\Controllers\HubController::class, 'productStock'])->whereNumber('id')->whereNumber('product')->name('hubs.product-stock');
    Route::post('/hubs/{id}/stock', [\App\Http\Controllers\HubController::class, 'stock'])->whereNumber('id')->name('hubs.stock');
    Route::post('/hubs/{id}/send', [\App\Http\Controllers\HubController::class, 'send'])->whereNumber('id')->name('hubs.send');
    Route::post('/hubs/{id}/remove', [\App\Http\Controllers\HubController::class, 'archive'])->whereNumber('id')->name('hubs.archive');
    Route::post('/hub-deliveries/{id}/resolve', [\App\Http\Controllers\HubController::class, 'resolve'])->whereNumber('id')->name('hubs.resolve');
    Route::get('/list-suppliers', function () {abort_unless(auth()->user()->active && auth()->user()->hasAccess('hubs'),403);return redirect()->route('hubs.index');});
});

//STOCK IN TAB
Route::middleware('access:stock')->group(function () {
    Route::get('/stock', [StockInController::class, 'overview'])->name('stock_index');
    Route::post('/add-stock', [StockInController::class, 'store'])->name('add_stock');
    Route::post('/quick-stock-update', [StockInController::class, 'quickUpdate'])->name('quick_stock_update');
    Route::get('/stock-history', [StockInController::class, 'history'])->name('stock_history');
    Route::get('/stock/inventory-count-report', [StockInController::class, 'inventoryCountReport'])->name('inventory_count_report');
});

//REPORTS TAB
Route::middleware('access:reporting')->group(function () {
    Route::get('/reports/profit-margin', [ReportController::class, 'profitMargin'])->name('report_profit');
});

//REFUND TAB
Route::middleware('access:refund')->group(function () {
    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds');
    Route::get('/refund-order/{id}', [RefundController::class, 'showOrder'])->name('refund_order');
    Route::post('/refund-order/{id}', [RefundController::class, 'store']);
});

//SHOPS TAB
Route::middleware('access:shops')->group(function () {
    Route::get('/shops', [ShopController::class, 'index'])->name('shops');
    Route::post('/add-shop', [ShopController::class, 'store'])->name('add_shop');
    Route::post('/update-shop/{id}', [ShopController::class, 'update'])->name('update_shop');
    Route::delete('/delete-shop', [ShopController::class, 'destroy'])->name('delete_shop');
});

//PAYMENT TYPES TAB
Route::middleware('access:payment_types')->group(function () {
    Route::get('/payment-types', [PaymentTypeController::class, 'index'])->name('payment_types');
    Route::post('/add-payment-type', [PaymentTypeController::class, 'store'])->name('add_payment_type');
    Route::post('/update-payment-type/{id}', [PaymentTypeController::class, 'update'])->name('update_payment_type');
    Route::delete('/delete-payment-type', [PaymentTypeController::class, 'destroy'])->name('delete_payment_type');
});

//BACKUP TAB
Route::middleware('access:backup')->group(function () {
    Route::get('/backup', [BackupController::class, 'index'])->name('backup');
    Route::post('/backup/create', [BackupController::class, 'create'])->name('backup_create');
    Route::post('/backup/google-setup', [BackupController::class, 'setupGoogle'])->name('backup_google_setup');
    Route::post('/backup/google-connect', [BackupController::class, 'connectGoogle'])->name('backup_google_connect');
    Route::get('/backup/google-callback', [BackupController::class, 'googleCallback'])->name('backup_google_callback');
    Route::post('/backup/google-disconnect', [BackupController::class, 'disconnectGoogle'])->name('backup_google_disconnect');
    Route::post('/backup/verify', [BackupController::class, 'verify'])->name('backup_verify');
    Route::post('/backup/restore', [BackupController::class, 'restore'])->name('backup_restore');
    Route::get('/backup/recovery-key', [BackupController::class, 'recoveryKey'])->name('backup_recovery_key');
    Route::get('/backup/download/{file}', [BackupController::class, 'download'])->name('backup_download');
    Route::delete('/backup/delete', [BackupController::class, 'destroy'])->name('backup_delete');
});


//POS TAB
Route::get('/pos', [PosController::class, 'index'])->name('pos');

//CART TAB
Route::post('/add-cart', [CartController::class, 'index'])->block(30, 30);
Route::post('/update-cart/{rowId}', [CartController::class, 'updateCart'])->block(30, 30);
Route::post('/delete-cart/{rowId}', [CartController::class, 'deleteCart'])->middleware('access:void_item')->block(30, 30);
Route::post('/delete-all', [CartController::class, 'deleteAll'])->middleware('access:void_order')->block(30, 30);
Route::post('/create-invoice', [CartController::class, 'createInvoice'])->block(30, 30);
Route::post('/add-transaction', [CartController::class, 'addTransaction'])->block(30, 30);

//TRANSACTION TAB
Route::get('/transaction-history', [TransactionController::class, 'index'])->name('transaction')->middleware('access:view_sales_history');
Route::get('/view-receipt/{id}', [TransactionController::class, 'viewReceipt'])->name('view_receipt');
Route::get('/receipt-settings', [\App\Http\Controllers\ReceiptSettingsController::class, 'index'])->name('receipt_settings');
Route::post('/receipt-settings/{id}', [\App\Http\Controllers\ReceiptSettingsController::class, 'update'])->name('update_receipt_settings');

// Shop-to-shop requests are available to cashiers, managers and the owner.
Route::middleware(['auth', 'access:stock_transfers'])->group(function () {
    Route::post('/hub-deliveries/{id}/receive', [\App\Http\Controllers\StockTransferController::class, 'receiveHub'])->whereNumber('id')->name('hubs.receive');
    Route::get('/stock-requests/alerts', [\App\Http\Controllers\StockTransferController::class, 'alerts'])->name('transfers.alerts');
    Route::get('/stock-requests', [\App\Http\Controllers\StockTransferController::class, 'index'])->name('transfers.index');
    Route::post('/stock-requests', [\App\Http\Controllers\StockTransferController::class, 'store'])->name('transfers.store');
    Route::post('/stock-requests/{id}', [\App\Http\Controllers\StockTransferController::class, 'update'])->whereNumber('id')->name('transfers.update');
});

// Workshop endpoints enforce active Owner/Manager access in the controller and service.
Route::middleware('auth')->group(function(){
 Route::get('/workshop',[\App\Http\Controllers\WorkshopController::class,'index'])->name('workshop.index');
 Route::get('/workshop/availability',[\App\Http\Controllers\WorkshopController::class,'availability'])->name('workshop.availability');
 Route::post('/workshop/fundis',[\App\Http\Controllers\WorkshopController::class,'fundi'])->name('workshop.fundis.create');
 Route::post('/workshop/fundis/{id}',[\App\Http\Controllers\WorkshopController::class,'fundi'])->whereNumber('id')->name('workshop.fundis.update');
 Route::post('/workshop/production',[\App\Http\Controllers\WorkshopController::class,'production'])->name('workshop.production');
 Route::post('/workshop/payments',[\App\Http\Controllers\WorkshopController::class,'payment'])->name('workshop.payment');
 Route::post('/workshop/components',[\App\Http\Controllers\WorkshopController::class,'components'])->name('workshop.components');
});

Route::middleware('auth')->group(function(){
 Route::get('/hubs/transfers',[\App\Http\Controllers\HubTransferController::class,'index'])->name('hubs.transfer-page');
 Route::post('/hubs/{id}/transfer',[\App\Http\Controllers\HubTransferController::class,'send'])->whereNumber('id')->name('hubs.transfer-send');
 Route::post('/hub-transfers/{id}/complete',[\App\Http\Controllers\HubTransferController::class,'complete'])->whereNumber('id')->name('hubs.transfer-complete');
});

Route::get('/list-of-products', [ProductsController::class, 'listOfProducts'])->middleware(['auth','access:view_products'])->name('list_product');
Route::post('/products/{id}/price', [ProductsController::class, 'updatePrice'])->whereNumber('id')->middleware(['auth','access:edit_prices'])->name('products.update-price');
