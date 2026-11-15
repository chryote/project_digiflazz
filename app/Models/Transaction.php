<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ref_id',
        'buyer_sku_code',
        'customer_no',
        'customer_name',
        'admin',
        'price',
        'selling_price',
        'buyer_last_saldo',
        'sn',
        'status',
        'rc',
        'message',
        'desc',
        'callback_url',
        'callback_status',
        'callback_payload',
    ];

    protected $casts = [
        'desc' => 'array',
        'callback_payload' => 'array',
        'admin' => 'integer',
        'price' => 'integer',
        'selling_price' => 'integer',
        'buyer_last_saldo' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
