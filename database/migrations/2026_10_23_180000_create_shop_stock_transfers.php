<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('product_stock', fn (Blueprint $t) => $t->integer('reserved_quantity')->default(0));
        Schema::create('stock_transfers', function (Blueprint $t) {
            $t->id(); $t->uuid('request_key')->unique();
            $t->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $t->foreignId('from_shop_id')->constrained('shops')->restrictOnDelete();
            $t->foreignId('to_shop_id')->constrained('shops')->restrictOnDelete();
            $t->unsignedInteger('requested_quantity'); $t->unsignedInteger('accepted_quantity')->nullable();
            $t->string('status', 20)->default('requested'); $t->string('note', 500)->nullable();
            $t->timestamps();
            $t->index(['from_shop_id', 'status']); $t->index(['to_shop_id', 'status']);
        });
        Schema::create('stock_transfer_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $t->string('action', 20); $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor_name'); $t->unsignedInteger('quantity')->nullable(); $t->string('note', 500)->nullable(); $t->timestamp('created_at');
        });
    }
    public function down(): void {
        Schema::dropIfExists('stock_transfer_events'); Schema::dropIfExists('stock_transfers');
        Schema::table('product_stock', fn (Blueprint $t) => $t->dropColumn('reserved_quantity'));
    }
};
