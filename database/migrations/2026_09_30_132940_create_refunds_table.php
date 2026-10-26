<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('transaction_code')->nullable();
            $table->string('customer_name')->nullable();
            $table->text('reason')->nullable();
            $table->decimal('total_refunded', 12, 2)->default(0);
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('refunds');
    }
};
