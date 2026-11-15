<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlnCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_no',
        'customer_name',
        'meter_no',
        'subscriber_id',
        'segment_power',
        'buyer_sku_code',
        'admin',
        'price',
        'selling_price',
        'desc',
        'behavior',
    ];

    protected $casts = [
        'desc' => 'array',
        'admin' => 'integer',
        'price' => 'integer',
        'selling_price' => 'integer',
    ];
}
