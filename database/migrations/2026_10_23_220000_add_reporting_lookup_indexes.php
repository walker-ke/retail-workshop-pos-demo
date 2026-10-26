<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('order_details', fn (Blueprint $t) => $t->index('order_id', 'order_details_order_lookup'));
        Schema::table('orders', function (Blueprint $t) {
            $t->index('order_date', 'orders_date_lookup');
            $t->index(['shop_id', 'order_date'], 'orders_shop_date_lookup');
        });
        Schema::table('stock_transfers', fn (Blueprint $t) => $t->index('status', 'transfers_status_lookup'));
    }
    public function down(): void {
        Schema::table('stock_transfers', fn (Blueprint $t) => $t->dropIndex('transfers_status_lookup'));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex('orders_date_lookup'); $t->dropIndex('orders_shop_date_lookup');
        });
        Schema::table('order_details', fn (Blueprint $t) => $t->dropIndex('order_details_order_lookup'));
    }
};
