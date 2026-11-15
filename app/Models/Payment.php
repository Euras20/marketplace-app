<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
        
    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->public_id)) {
                $payment->public_id = (string) Str::ulid();
            }
        });
    }

    protected $fillable = [
        'public_id',
        'store_id',
        'order_id',
        'method_code',
        'amount',
        'currency_code',
        'status',
        'submitted_at',
        'verified_at',
        'verified_by_admin_id',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function verificationHistory(): HasMany
    {
        return $this->hasMany(PaymentVerificationHistory::class);
    }
}
