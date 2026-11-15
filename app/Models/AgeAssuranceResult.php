<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgeAssuranceResult extends Model
{
    protected $fillable = [
        'store_id',
        'subject_type',
        'subject_identifier',
        'provider',
        'result',
        'assertion_hash',
        'verified_at',
        'expires_at',
        'evidence_metadata',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'evidence_metadata' => 'array',
    ];

    public const RESULT_PENDING = 'pending';
    public const RESULT_ELIGIBLE = 'eligible';
    public const RESULT_INELIGIBLE = 'ineligible';

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isValid(): bool
    {
        return $this->result === self::RESULT_ELIGIBLE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
