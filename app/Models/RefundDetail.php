<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundDetail extends Model
{
    use HasFactory;
    protected $table = 'refund_details';
    protected $fillable = [
        'refund_id',
        'product_id',
        'quantity',
        'unit_price',
        'total',
    ];
}
