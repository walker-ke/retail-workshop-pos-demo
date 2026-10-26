<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;
    protected $table = 'refunds';
    protected $fillable = [
        'order_id',
        'transaction_code',
        'customer_name',
        'reason',
        'total_refunded',
    ];
}
