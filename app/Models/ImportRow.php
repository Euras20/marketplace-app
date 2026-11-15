<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $fillable = [
        'store_id',
        'import_job_id',
        'row_number',
        'sku',
        'payload',
        'status',
        'error_codes',
        'error_messages',
        'created_product_id',
    ];

    protected $casts = [
        'error_codes' => 'array',
        'error_messages' => 'array',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'created_product_id');
    }
}
