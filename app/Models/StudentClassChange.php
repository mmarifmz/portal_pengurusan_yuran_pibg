<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class StudentClassChange extends Model
{
    protected $fillable = [
        'student_id',
        'old_class_name',
        'new_class_name',
        'reason',
        'changed_by_user_id',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public static function tableIsAvailable(): bool
    {
        return Schema::hasTable((new self)->getTable());
    }
}
