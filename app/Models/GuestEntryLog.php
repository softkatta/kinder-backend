<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestEntryLog extends Model
{
    protected $fillable = [
        'guest_id', 'guest_companion_id', 'person_name', 'direction',
        'scanned_at', 'scanned_by_user_id', 'notes',
    ];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function companion(): BelongsTo
    {
        return $this->belongsTo(GuestCompanion::class, 'guest_companion_id');
    }

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }
}
