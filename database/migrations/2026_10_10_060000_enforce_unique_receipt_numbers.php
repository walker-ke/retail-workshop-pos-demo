<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up()
    {
        // Fail safely on historical duplicates; never change existing receipt numbers.
        Schema::table('orders', fn (Blueprint $table) => $table->unique('transaction_code'));
    }
    public function down()
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropUnique('orders_transaction_code_unique'));
    }
};
