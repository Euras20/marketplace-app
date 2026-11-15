<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestOrderClaim extends Model
{
    protected $fillable = [
        'store_id',
        'order_id',
        'email',
        'claim_token_hash',
        'claimed_at',
        'claimed_by_customer_id',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'claimed_by_customer_id');
    }
}
