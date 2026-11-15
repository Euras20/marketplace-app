<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Support\Str;


class ProductVariant extends Model
{
    

    protected $fillable = [
        'store_id',
        'product_id',
        'sku',
        'variant_name',
        'price',
        'compare_at_price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    
protected static function booted(): void
{
    static::creating(function (ProductVariant $variant) {
        if (empty($variant->public_id)) {
            $variant->public_id = (string) Str::ulid();
        }
    });
}

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class, 'variant_id');
    }

    public function inventory(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Inventory::class, 'product_variant_id');
    }
}
