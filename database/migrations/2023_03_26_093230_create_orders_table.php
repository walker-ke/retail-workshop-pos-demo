<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->integer('transaction_code');
            $table->string('customer_name');
            $table->integer('customer_id');
            $table->string('order_date');
            $table->string('order_status');
            $table->string('total_products');
            $table->string('sub_total');
            $table->string('tax');
            $table->string('total');
            $table->string('paid_amount');
            $table->string('payment_method');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
