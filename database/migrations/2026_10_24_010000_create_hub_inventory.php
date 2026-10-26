<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('hubs', function(Blueprint $t){$t->id();$t->string('name')->unique();$t->boolean('is_active')->default(true);$t->timestamps();});
        Schema::create('hub_stock', function(Blueprint $t){$t->id();$t->foreignId('hub_id')->constrained('hubs')->restrictOnDelete();$t->foreignId('product_id')->constrained('products')->restrictOnDelete();$t->integer('quantity')->default(0);$t->unique(['hub_id','product_id']);});
        Schema::create('hub_deliveries',function(Blueprint $t){
            $t->id();$t->uuid('request_key')->unique();$t->string('request_hash',64);
            $t->foreignId('hub_id')->constrained('hubs')->restrictOnDelete();$t->foreignId('shop_id')->constrained('shops')->restrictOnDelete();$t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->unsignedInteger('quantity');$t->unsignedInteger('received_quantity')->nullable();$t->string('status',20)->default('dispatched');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->string('sender_name');
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();$t->string('receiver_name')->nullable();$t->uuid('receive_key')->nullable()->unique();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();$t->string('resolver_name')->nullable();$t->uuid('resolve_key')->nullable()->unique();$t->string('resolve_hash',64)->nullable();$t->string('resolution',20)->nullable();
            $t->text('note')->nullable();$t->text('receipt_note')->nullable();$t->text('resolution_note')->nullable();$t->timestamp('received_at')->nullable();$t->timestamp('resolved_at')->nullable();$t->timestamps();
            $t->index('status');$t->index(['shop_id','status']);$t->index(['hub_id','status']);
        });
        Schema::create('hub_stock_events',function(Blueprint $t){
            $t->id();$t->foreignId('hub_id')->constrained('hubs')->restrictOnDelete();$t->foreignId('product_id')->constrained('products')->restrictOnDelete();$t->foreignId('delivery_id')->nullable()->constrained('hub_deliveries')->restrictOnDelete();
            $t->integer('delta');$t->integer('balance');$t->string('action',30);$t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();$t->string('actor_name');$t->text('note')->nullable();$t->uuid('request_key')->nullable()->unique();$t->string('request_hash',64)->nullable();$t->timestamp('created_at');
        });
        DB::statement('ALTER TABLE hub_stock ADD CONSTRAINT hub_stock_nonnegative CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE hub_deliveries ADD CONSTRAINT hub_delivery_quantities CHECK (quantity > 0 AND (received_quantity IS NULL OR (received_quantity >= 0 AND received_quantity <= quantity)))');
        DB::table('hubs')->insert(array_map(fn($name)=>['name'=>$name,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()],['NORTH DEMO HUB','SOUTH DEMO HUB']));
    }
    public function down(): void {Schema::dropIfExists('hub_stock_events');Schema::dropIfExists('hub_deliveries');Schema::dropIfExists('hub_stock');Schema::dropIfExists('hubs');}
};
