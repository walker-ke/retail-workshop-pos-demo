<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('id');
            $table->index('shop_id');
        });

        // Copy shop_id from the parent order, matching how this table already
        // denormalizes customer_name/transaction_code from the order.
        DB::statement(
            'UPDATE refunds
             JOIN orders ON orders.id = refunds.order_id
             SET refunds.shop_id = orders.shop_id'
        );

        $defaultShopId = DB::table('shops')->orderBy('id')->value('id');
        DB::table('refunds')->whereNull('shop_id')->update(['shop_id' => $defaultShopId]);

        DB::statement('ALTER TABLE refunds MODIFY shop_id BIGINT UNSIGNED NOT NULL');

        Schema::table('refunds', function (Blueprint $table) {
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }
};
