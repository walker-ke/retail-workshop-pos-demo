<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE orders MODIFY customer_name VARCHAR(255) NULL');
        DB::statement('ALTER TABLE orders MODIFY customer_id INT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE orders MODIFY customer_name VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE orders MODIFY customer_id INT NOT NULL');
    }
};
