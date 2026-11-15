<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePaymentSetting extends Model
{
    protected $fillable = [
        'store_id',
        'payment_method_code',
        'display_name',
        'instructions',
        'account_details',
        'is_enabled',
    ];

    protected $casts = [
        'account_details' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
