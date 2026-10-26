<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('access_level_settings')->insert([
            'operation_key' => 'shops',
            'category' => 'management',
            'label' => 'Shops',
            'description' => 'Allows user to open the shops module and manage shop locations',
            'enforced' => true,
            'sort_order' => 13,
            'required_level' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('access_level_settings')->where('operation_key', 'shops')->delete();
    }
};
