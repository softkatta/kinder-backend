<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    public const STATUSES = ['scheduled', 'ongoing', 'completed', 'published'];

    public const TYPES = ['unit', 'midterm', 'term', 'final', 'annual'];

    protected $fillable = [
        'tenant_id', 'academic_year_id', 'name', 'exam_type', 'class_name',
        'subject', 'exam_date', 'max_marks', 'status', 'remarks',
    ];

    protected function casts(): array
    {
        return ['exam_date' => 'date'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }
}
