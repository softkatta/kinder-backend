<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Admission extends Model
{
    public const STATUSES = ['pending', 'review', 'approved', 'rejected'];

    protected $fillable = [
        'tenant_id',
        'applicant_name',
        'dob',
        'gender',
        'grade_level',
        'parent_info',
        'address_info',
        'photo_path',
        'status',
        'remarks',
        'reviewed_by_user_id',
        'reviewed_at',
        'student_id_card_id',
        'parent_user_id',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'parent_info' => 'array',
            'address_info' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function studentIdCard(): BelongsTo
    {
        return $this->belongsTo(IdCard::class, 'student_id_card_id');
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }
}
