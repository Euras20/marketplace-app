<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    protected $table = 'inventory';

    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'product_variant_id',
        'on_hand',
        'reserved',
    ];

    protected $casts = [
        'on_hand' => 'integer',
        'reserved' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_variant_id', 'product_variant_id');
    }

    public function getAvailableAttribute(): int
    {
        return $this->on_hand - $this->reserved;
    }
}