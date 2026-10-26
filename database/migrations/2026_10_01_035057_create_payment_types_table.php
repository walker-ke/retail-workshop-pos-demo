<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('position')->default(0);
            $table->string('code')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('quick_payment')->default(false);
            $table->boolean('customer_required')->default(false);
            $table->boolean('change_allowed')->default(false);
            $table->boolean('mark_transaction_as_paid')->default(true);
            $table->boolean('print_receipt')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('payment_types')->insert([
            ['name' => 'Cash', 'position' => 1, 'code' => null, 'enabled' => true, 'quick_payment' => true, 'customer_required' => false, 'change_allowed' => true, 'mark_transaction_as_paid' => true, 'print_receipt' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Card', 'position' => 2, 'code' => null, 'enabled' => true, 'quick_payment' => true, 'customer_required' => false, 'change_allowed' => false, 'mark_transaction_as_paid' => true, 'print_receipt' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Check', 'position' => 3, 'code' => null, 'enabled' => true, 'quick_payment' => true, 'customer_required' => false, 'change_allowed' => false, 'mark_transaction_as_paid' => true, 'print_receipt' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('payment_types');
    }
};
