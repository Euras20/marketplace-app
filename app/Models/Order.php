<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
        protected $fillable = [
        'public_id',
        'store_id',
        'customer_id',
        'order_number',
        'status',
        'payment_status',
        'currency_code',
        'subtotal',
        'delivery_fee',
        'discount_total',
        'grand_total',
        'contact_email',
        'contact_phone',
        'contact_name',
        'delivery_address_snapshot',
        'reservation_expires_at',
        'approved_at',
        'cancelled_at',
    ];

    
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->public_id)) {
                $order->public_id = (string) Str::ulid();
            }
        });
    }

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'delivery_address_snapshot' => 'array',
        'reservation_expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAYMENT_REVIEW = 'payment_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_IN_PREPARATION = 'in_preparation';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class);
    }

    public function tracking(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderTracking::class);
    }
}
