<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use HasFactory;
    protected $table = 'payment_types';
    protected $fillable = [
        'name',
        'position',
        'code',
        'enabled',
        'quick_payment',
        'customer_required',
        'change_allowed',
        'mark_transaction_as_paid',
        'print_receipt',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'quick_payment' => 'boolean',
        'customer_required' => 'boolean',
        'change_allowed' => 'boolean',
        'mark_transaction_as_paid' => 'boolean',
        'print_receipt' => 'boolean',
    ];
}
