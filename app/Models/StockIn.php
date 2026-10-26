<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockIn extends Model
{
    use HasFactory;
    protected $table = 'stock_ins';
    protected $fillable = [
        'product_id',
        'supplier_id',
        'quantity',
        'cost_price',
        'reference',
        'notes',
    ];
}
