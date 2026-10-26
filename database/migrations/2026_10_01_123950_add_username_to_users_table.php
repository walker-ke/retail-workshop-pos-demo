<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
        });

        // Temp-worker accounts created during peak sale times may not have a
        // real email - username alone is enough to log in, so email can no
        // longer be required. MySQL allows multiple NULLs in a unique index,
        // so existing emails stay protected from duplicates.
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
