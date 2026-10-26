<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_ins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->integer('quantity');
            $table->integer('cost_price')->default(0);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index('supplier_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_ins');
    }
};
