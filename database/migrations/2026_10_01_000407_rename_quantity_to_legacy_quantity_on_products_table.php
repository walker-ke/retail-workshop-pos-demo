<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * products.quantity is renamed rather than dropped: it's kept as a
     * read-only snapshot of pre-multi-shop stock for sanity-checking, and
     * any code path not yet migrated to product_stock will now fail loudly
     * (unknown column) instead of silently writing to a dead number.
     */
    public function up()
    {
        DB::statement('ALTER TABLE products CHANGE quantity legacy_quantity INT NOT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE products CHANGE legacy_quantity quantity INT NOT NULL');
    }
};
