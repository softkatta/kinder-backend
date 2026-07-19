<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdCardScanLog extends Model
{
    protected $fillable = [
        'tenant_id', 'id_card_id', 'qr_token', 'result',
        'scanned_by', 'payload', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function idCard(): BelongsTo
    {
        return $this->belongsTo(IdCard::class);
    }

    public function scannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
