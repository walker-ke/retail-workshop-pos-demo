<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  DB::statement("ALTER TABLE workshop_production ADD CONSTRAINT workshop_production_valid CHECK (quantity > 0 AND rate > 0 AND amount = quantity * rate AND stage IN ('tailor','uppers','assembly'))");
  DB::statement("ALTER TABLE workshop_payments ADD CONSTRAINT workshop_payment_valid CHECK (amount > 0 AND type IN ('advance','payout'))");
  DB::statement("ALTER TABLE workshop_ledger ADD CONSTRAINT workshop_ledger_valid CHECK ((type = 'earning' AND amount > 0) OR (type IN ('advance','payout') AND amount < 0) OR type = 'opening')");
  DB::statement("ALTER TABLE workshop_component_stock ADD CONSTRAINT workshop_component_nonnegative CHECK (quantity >= 0)");
 }
 public function down(): void {foreach(['workshop_production'=>'workshop_production_valid','workshop_payments'=>'workshop_payment_valid','workshop_ledger'=>'workshop_ledger_valid','workshop_component_stock'=>'workshop_component_nonnegative'] as $table=>$constraint)DB::statement('ALTER TABLE '.$table.' DROP CONSTRAINT '.$constraint);}
};
