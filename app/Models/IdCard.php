<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdCard extends Model
{
    public const TYPES = ['student', 'teacher', 'staff', 'parent', 'guest'];

    public const STATUSES = ['active', 'inactive', 'blocked', 'expired'];

    protected $fillable = [
        'tenant_id', 'card_type', 'card_number', 'qr_token', 'scan_code', 'status',
        'full_name', 'photo_path', 'blood_group', 'academic_year',
        'issue_date', 'expiry_date', 'emergency_contact',         'meta', 'user_id', 'transport_route_id',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transportRoute(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class);
    }

    public function studentFees(): HasMany
    {
        return $this->hasMany(StudentFee::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(IdCardScanLog::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function isScannable(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    public function roleLabel(): string
    {
        return match ($this->card_type) {
            'student' => 'Student',
            'teacher' => 'Teacher',
            'staff' => 'Staff',
            'parent' => 'Parent',
            'guest' => 'Guest Pass',
            default => ucfirst($this->card_type),
        };
    }
}
