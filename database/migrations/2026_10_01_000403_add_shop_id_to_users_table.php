<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('access_level');
            $table->index('shop_id');
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('restrict');
        });

        $defaultShopId = DB::table('shops')->orderBy('id')->value('id');
        DB::table('users')->update(['shop_id' => $defaultShopId]);

        $maxLevel = DB::table('users')->max('access_level');
        DB::table('users')->where('access_level', $maxLevel)->update(['shop_id' => null]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropColumn('shop_id');
        });
    }
};
