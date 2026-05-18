<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'previous_roles',
    'new_role',
    'class_name',
    'assigned_by',
    'assigned_at',
    'source',
    'meta',
])]
class TeacherRoleAssignmentAudit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_roles' => 'array',
            'assigned_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
