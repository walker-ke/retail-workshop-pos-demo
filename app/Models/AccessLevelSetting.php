<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessLevelSetting extends Model
{
    use HasFactory;
    protected $table = 'access_level_settings';
    protected $fillable = [
        'operation_key',
        'category',
        'label',
        'description',
        'enforced',
        'sort_order',
        'required_level',
    ];

    protected $casts = [
        'enforced' => 'boolean',
    ];
}
