<?php
namespace Database\Seeders;

use App\Models\User;
use App\Services\HubStockService;
use App\Services\WorkshopService;
use App\Support\ReceiptData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!config('demo.enabled')) throw new RuntimeException('Synthetic seeding requires DEMO_MODE=true.');
        if (DB::table('users')->exists() || DB::table('products')->exists() || DB::table('orders')->exists()) {
            throw new RuntimeException('Refusing to seed an existing dataset. Use demo:reset only on a disposable *_demo database.');
        }
        DB::transaction(function () {
            $now = now();
            $first = (int) DB::table('shops')->value('id');
            DB::table('shops')->where('id', $first)->update(['name'=>'North Demo Shop', 'receipt_name'=>'Demo Retail Workshop', 'receipt_address'=>'Fictional North Arcade', 'receipt_footer'=>'SYNTHETIC DEMO — NOT A TAX INVOICE']);
            $second = DB::table('shops')->insertGetId(['name'=>'South Demo Shop','address'=>'Fictional South Arcade','receipt_name'=>'Demo Retail Workshop','receipt_footer'=>'SYNTHETIC DEMO — NOT A TAX INVOICE','is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
            // Public fixture password for isolated synthetic accounts only; never use on real systems.
            $hash = Hash::make('DemoWorkshop2026!');
            foreach ([['owner',User::OWNER,$first],['manager',User::MANAGER,$first],['cashier',User::CASHIER,$first],['south-cashier',User::CASHIER,$second]] as [$name,$role,$shop]) {
                User::create(['name'=>'Demo '.ucwords(str_replace('-',' ',$name)), 'username'=>$name, 'email'=>$name.'@example.test','password'=>$hash,'access_level'=>$role,'shop_id'=>$shop,'active'=>true]);
            }
            $manager = User::where('username','manager')->firstOrFail();
            $cashier = User::where('username','cashier')->firstOrFail();
            $hub = (int) DB::table('hubs')->value('id');
            foreach (['Shirts','Trousers','Shoes','Sweaters'] as $group) DB::table('brands')->insert(['brand_name'=>$group,'manufacturer'=>'Fictional Demo Workshop','created_at'=>$now,'updated_at'=>$now]);
            $products = [];
            foreach ([['Sky blue shirt',950,550,'Shirts'],['Navy trousers',1450,800,'Trousers'],['Black school shoes',2200,1200,'Shoes'],['Burgundy sweater',1600,900,'Sweaters']] as $g=>[$name,$price,$cost,$brand]) {
                foreach ([24,28,32] as $i=>$size) {
                    $id = DB::table('products')->insertGetId(['product_name'=>$name.' size '.$size,'product_code'=>'DEMO-'.($g+1).'-'.$size,'brand'=>$brand,'price'=>$price,'cost_price'=>$cost,'legacy_quantity'=>0,'description'=>'Invented portfolio catalog item','created_at'=>$now,'updated_at'=>$now]);
                    $products[] = $id;
                    foreach ([$first,$second] as $shop) DB::table('product_stock')->insert(['product_id'=>$id,'shop_id'=>$shop,'quantity'=>20+$i*5,'reserved_quantity'=>0,'created_at'=>$now,'updated_at'=>$now]);
                    app(HubStockService::class)->stock($manager,$hub,$id,40,'add',(string)Str::uuid(),'Synthetic opening hub stock');
                }
            }
            DB::table('payment_types')->whereIn('name',['Card','Check'])->update(['enabled'=>false]);
            DB::table('payment_types')->insert(['name'=>'Paybill','position'=>2,'enabled'=>true,'quick_payment'=>true,'customer_required'=>false,'change_allowed'=>false,'mark_transaction_as_paid'=>true,'print_receipt'=>true,'created_at'=>$now,'updated_at'=>$now]);
            for ($day=0; $day<10; $day++) {
                $product=DB::table('products')->where('id',$products[$day%count($products)])->first();
                $date=$now->copy()->subDays($day);$quantity=1+($day%3);$total=$product->price*$quantity;$shop=$day%2?$second:$first;
                $order=DB::table('orders')->insertGetId(['shop_id'=>$shop,'cashier_id'=>$cashier->id,'cashier_name'=>'Demo Cashier','receipt_settings'=>json_encode(['name'=>'Demo Retail Workshop','footer'=>'SYNTHETIC DEMO — NOT A TAX INVOICE']),'customer_name'=>'Fictional Customer '.($day+1),'customer_id'=>null,'order_date'=>$date->timezone('Africa/Nairobi')->format('d/m/y'),'order_status'=>'Paid','total_products'=>$quantity,'sub_total'=>$total,'tax'=>0,'total'=>$total,'paid_amount'=>$total,'payment_method'=>$day%2?'Paybill':'Cash','transaction_code'=>700000001+$day,'created_at'=>$date,'updated_at'=>$date]);
                DB::table('order_details')->insert(['order_id'=>$order,'product_id'=>$product->id,'product_name'=>$product->product_name,'product_code'=>$product->product_code,'quantity'=>$quantity,'unit_price'=>$product->price,'total'=>$total]);
                DB::table('product_stock')->where('shop_id',$shop)->where('product_id',$product->id)->decrement('quantity',$quantity);
            }
            $fundi=DB::table('workshop_fundis')->insertGetId(['name'=>'Fictional Maker A','skills'=>json_encode(['tailor','uppers','assembly']),'tailor_rate'=>12000,'uppers_rate'=>8000,'assembly_rate'=>18000,'request_key'=>(string)Str::uuid(),'is_active'=>true,'created_at'=>$now,'updated_at'=>$now]);
            foreach ([['tailor',$products[0],5],['uppers',$products[6],12],['assembly',$products[6],3]] as [$stage,$product,$quantity]) {
                app(WorkshopService::class)->production($manager,['fundi_id'=>$fundi,'hub_id'=>$hub,'product_id'=>$product,'stage'=>$stage,'quantity'=>$quantity,'work_date'=>now('Africa/Nairobi')->toDateString(),'request_key'=>(string)Str::uuid()]);
            }
            app(WorkshopService::class)->payment($manager,['fundi_id'=>$fundi,'type'=>'advance','amount'=>'200.00','payment_date'=>now('Africa/Nairobi')->toDateString(),'method'=>'Demo cash record','reference'=>'FICTIONAL-ADVANCE','request_key'=>(string)Str::uuid()]);
            app(HubStockService::class)->send($manager,$hub,$second,$products[0],4,(string)Str::uuid(),'Synthetic delivery — try receiving as south-cashier');
        });
    }
}
