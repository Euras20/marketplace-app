<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'product_variant_id',
        'order_id',
        'movement_type',
        'quantity',
        'on_hand_after',
        'reserved_after',
        'reason',
        'actor_type',
        'actor_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public const TYPE_RESERVATION = 'reservation';
    public const TYPE_RESERVATION_RELEASE = 'reservation_release';
    public const TYPE_SALE_DEDUCTION = 'sale_deduction';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';
    public const TYPE_RETURN_RESTOCK = 'return_restock';

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
