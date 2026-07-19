<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    public const STATUSES = ['active', 'inactive', 'blocked', 'expired'];

    protected $fillable = [
        'tenant_id', 'user_id', 'guest_code', 'qr_token', 'scan_code', 'full_name', 'phone', 'email', 'photo_path',
        'event_name', 'event_date', 'event_location', 'valid_from', 'valid_until', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'valid_from' => 'date',
            'valid_until' => 'date',
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

    public function companions(): HasMany
    {
        return $this->hasMany(GuestCompanion::class)->orderBy('sort_order');
    }

    public function entryLogs(): HasMany
    {
        return $this->hasMany(GuestEntryLog::class)->latest('scanned_at');
    }

    public function isExpired(): bool
    {
        return $this->valid_until->endOfDay()->isPast();
    }

    public function isNotYetValid(): bool
    {
        return $this->valid_from->startOfDay()->isFuture();
    }

    public function isScannable(): bool
    {
        return $this->status === 'active' && ! $this->isNotYetValid() && ! $this->isExpired();
    }
}
