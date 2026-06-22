<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSocialTag extends Model
{
    protected $fillable = [
        'student_id',
        'social_tag_id',
        'assigned_by',
        'notes',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function socialTag(): BelongsTo
    {
        return $this->belongsTo(SocialTag::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
