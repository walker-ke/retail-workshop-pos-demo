<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('shop_id');
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'shop_id']);
            $table->index('shop_id');

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
        });

        $defaultShopId = DB::table('shops')->orderBy('id')->value('id');

        $rows = DB::table('products')->select('id', 'quantity')->get();
        $now = now();
        $insert = $rows->map(function ($product) use ($defaultShopId, $now) {
            return [
                'product_id' => $product->id,
                'shop_id' => $defaultShopId,
                'quantity' => $product->quantity,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        foreach (array_chunk($insert, 500) as $chunk) {
            DB::table('product_stock')->insert($chunk);
        }
    }

    public function down()
    {
        Schema::dropIfExists('product_stock');
    }
};
