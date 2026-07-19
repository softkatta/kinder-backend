<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResult extends Model
{
    protected $fillable = [
        'tenant_id', 'exam_id', 'student_name', 'roll_number', 'class_name',
        'marks_obtained', 'grade', 'result_status', 'remarks',
        'marksheet_printed_at', 'certificate_printed_at',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:2',
            'marksheet_printed_at' => 'datetime',
            'certificate_printed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
