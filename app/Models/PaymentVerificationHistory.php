<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVerificationHistory extends Model
{
    protected $table = 'payment_verification_history';

    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'payment_id',
        'decision',
        'reason',
        'admin_user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
