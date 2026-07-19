<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestCompanion extends Model
{
    protected $fillable = [
        'guest_id', 'full_name', 'phone', 'photo_path', 'relation', 'can_entry', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['can_entry' => 'boolean'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function entryLogs(): HasMany
    {
        return $this->hasMany(GuestEntryLog::class);
    }
}
