<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('workshop_fundis',fn(Blueprint $table)=>$table->json('tailoring_rates')->nullable());
  Schema::table('workshop_production',fn(Blueprint $table)=>$table->string('garment_type',40)->nullable());
 }
 public function down(): void {
  Schema::table('workshop_production',fn(Blueprint $table)=>$table->dropColumn('garment_type'));
  Schema::table('workshop_fundis',fn(Blueprint $table)=>$table->dropColumn('tailoring_rates'));
 }
};
