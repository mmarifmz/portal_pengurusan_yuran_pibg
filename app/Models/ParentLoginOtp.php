<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentLoginOtp extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'code_hash',
        'channel',
        'expires_at',
        'used_at',
        'attempts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}