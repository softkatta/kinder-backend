<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogService
{
    /** @param array<string, mixed>|null $meta */
    public function log(
        ?User $user,
        string $action,
        string $summary,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $meta = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'summary' => mb_substr($summary, 0, 500),
            'meta' => $meta,
            'ip_address' => $request?->ip(),
        ]);
    }
}
