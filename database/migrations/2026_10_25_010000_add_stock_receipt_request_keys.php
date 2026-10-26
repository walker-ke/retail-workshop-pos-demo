<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('stock_ins',function(Blueprint $t){$t->uuid('request_key')->nullable()->unique();$t->string('request_hash',64)->nullable();});}
 public function down(): void {Schema::table('stock_ins',function(Blueprint $t){$t->dropUnique(['request_key']);$t->dropColumn(['request_key','request_hash']);});}
};
