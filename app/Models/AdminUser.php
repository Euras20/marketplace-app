<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class AdminUser extends Authenticatable
{
    protected $fillable = [
        'public_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected static function booted(): void
    {
        static::creating(function (AdminUser $user) {
            if (empty($user->public_id)) {
                $user->public_id = (string) Str::ulid();
            }
        });
    }
}