<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'payment_id',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
