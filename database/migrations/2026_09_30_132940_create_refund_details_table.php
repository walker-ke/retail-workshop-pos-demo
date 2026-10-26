<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('refund_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('refund_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->integer('unit_price');
            $table->integer('total');
            $table->timestamps();

            $table->index('refund_id');
            $table->index('product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('refund_details');
    }
};
