<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::create('hub_transfers',function(Blueprint $t){
  $t->id();$t->foreignId('source_hub_id')->constrained('hubs')->restrictOnDelete();$t->foreignId('destination_hub_id')->constrained('hubs')->restrictOnDelete();$t->foreignId('product_id')->constrained('products')->restrictOnDelete();$t->string('kind',12);$t->unsignedInteger('quantity');$t->string('status',15)->default('in_transit');$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->string('sender_name');$t->uuid('request_key')->unique();$t->string('request_hash',64);$t->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();$t->string('completer_name')->nullable();$t->uuid('complete_key')->nullable()->unique();$t->string('complete_hash',64)->nullable();$t->timestamp('completed_at')->nullable();$t->timestamps();$t->index(['source_hub_id','status','id']);$t->index(['destination_hub_id','status','id']);
 });}
 public function down(): void {Schema::dropIfExists('hub_transfers');}
};
