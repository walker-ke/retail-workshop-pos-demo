<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Orders extends Model
{
    use HasFactory;

    protected $table= 'orders';

    protected $fillable =[
        'transaction_code',
        'customer_name',
        'customer_id',
        'order_date',
        'order_status',
        'total_products',
        'sub_total',
        'tax',
        'total',
        'paid_amount',
        'payment_method',
    ];
}
