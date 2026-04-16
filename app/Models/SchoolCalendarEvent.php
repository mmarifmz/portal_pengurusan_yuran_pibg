<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCalendarEvent extends Model
{
    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'day_label',
        'description',
        'notes',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'sort_order' => 'integer',
        ];
    }
}
