<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Store extends Model
{

    protected $fillable = [
    'public_id',
    'name',
    'code',
    'primary_domain',
    'status',
];

    protected $casts = [
        'status' => 'string',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
