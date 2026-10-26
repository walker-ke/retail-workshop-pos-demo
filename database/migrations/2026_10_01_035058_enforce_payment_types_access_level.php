<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('access_level_settings')
            ->where('operation_key', 'payment_types')
            ->update(['enforced' => true]);
    }

    public function down()
    {
        DB::table('access_level_settings')
            ->where('operation_key', 'payment_types')
            ->update(['enforced' => false]);
    }
};
