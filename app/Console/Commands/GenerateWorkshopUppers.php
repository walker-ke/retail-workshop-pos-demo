<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateWorkshopUppers extends Command
{
 protected $signature='workshop:generate-uppers {--dry-run : Count matching shoes without creating entries}';
 protected $description='Register an upper component for each existing shoe variant; never adds stock or earnings';
 public static function isShoe(string $name,string $brand=''): bool
 {
  $text=strtolower($name.' '.$brand);
  if(preg_match('/\b(laces?|shoelaces?|innersoles?|insoles?|polish|brush(?:es)?|accessories)\b/i',$name) && !preg_match('/\bshoes?\b/i',$name) && !preg_match('/\bschool\s+fees\s+(?:laces?|backle)\b/i',$name))return false;
  return (bool)preg_match('/\b(?:shoes?|loafers?|suedes?|toughees?|bushori|topen)\b|\bschool\s+fees\s+(?:backle|laces?)\b/i',$text);
 }
 public function handle(): int
 {
  $matched=0;$created=0;$skipped=0;
  DB::table('products')->orderBy('id')->chunkById(200,function($products)use(&$matched,&$created,&$skipped){
   foreach($products as $product){
    if(!self::isShoe($product->product_name,$product->brand??''))continue;
    $matched++;
    DB::transaction(function()use($product,&$created,&$skipped){
     DB::table('products')->where('id',$product->id)->lockForUpdate()->first();
     if(DB::table('workshop_production')->where('product_id',$product->id)->where('stage','tailor')->exists()){$skipped++;return;}
     if(!$this->option('dry-run'))$created+=DB::table('workshop_components')->insertOrIgnore(['product_id'=>$product->id,'kind'=>'uppers','created_at'=>now(),'updated_at'=>now()]);
    });
   }
  });
  $this->info("Shoe variants: {$matched}; upper entries created: {$created}; garment conflicts skipped: {$skipped}.");
  $this->line($this->option('dry-run')?'Dry run — no changes.':'Stock quantities and fundi earnings were not changed.');
  return self::SUCCESS;
 }
}
