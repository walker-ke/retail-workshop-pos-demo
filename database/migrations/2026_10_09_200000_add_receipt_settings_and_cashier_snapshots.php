<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            foreach (['name', 'pin', 'phone', 'address', 'paybill', 'account_number', 'till_number', 'footer'] as $field) {
                $table->string('receipt_'.$field)->nullable();
            }
        });
        DB::table('shops')->update(['receipt_phone' => DB::raw('phone'), 'receipt_address' => DB::raw('address')]);
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cashier_name')->nullable();
            $table->json('receipt_settings')->nullable();
        });
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('product_name')->nullable();
            $table->string('product_code')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('order_details', fn (Blueprint $table) => $table->dropColumn(['product_name', 'product_code']));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_id');
            $table->dropColumn(['cashier_name', 'receipt_settings']);
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(array_map(fn ($field) => 'receipt_'.$field, ['name', 'pin', 'phone', 'address', 'paybill', 'account_number', 'till_number', 'footer']));
        });
    }
};
