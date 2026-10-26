<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('stock_ins', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('id');
            $table->index('shop_id');
        });

        $defaultShopId = DB::table('shops')->orderBy('id')->value('id');
        DB::table('stock_ins')->update(['shop_id' => $defaultShopId]);

        DB::statement('ALTER TABLE stock_ins MODIFY shop_id BIGINT UNSIGNED NOT NULL');

        Schema::table('stock_ins', function (Blueprint $table) {
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('stock_ins', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }
};
