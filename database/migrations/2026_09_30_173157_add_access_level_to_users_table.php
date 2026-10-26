<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('access_level')->default(0)->after('email');
            $table->boolean('active')->default(true)->after('access_level');
        });

        // The first existing admin account becomes the unrestricted "master admin" (highest access level).
        DB::table('users')->orderBy('id')->limit(1)->update(['access_level' => 9]);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['access_level', 'active']);
        });
    }
};
