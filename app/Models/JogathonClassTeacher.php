<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JogathonClassTeacher extends Model
{
    protected $fillable = [
        'class_name',
        'teacher_name',
        'source',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }
}
