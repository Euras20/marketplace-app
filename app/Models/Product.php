<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;
    

    protected $fillable = [
        'public_id',
        'store_id',
        'category_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'status',
        'is_promo',
        'published_at',
        
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_promo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->public_id)) {
                $product->public_id = (string) Str::ulid();
            }
        });
    }

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';
    

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function media()
    {
        return $this->hasMany(ProductMedia::class);
    }
    public function attributeValues(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
    return $this->hasMany(\App\Models\ProductAttributeValue::class);
    }
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function canBePublished(): bool
    {
        return $this->variants()->where('is_active', true)->exists();
    }
}