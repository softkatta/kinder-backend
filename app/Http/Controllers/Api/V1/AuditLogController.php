<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('user:id,name,email')->latest();

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('summary', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            });
        }

        $rows = $query->limit(200)->get()->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'summary' => $log->summary,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'user_name' => $log->user?->name,
            'user_email' => $log->user?->email,
            'ip_address' => $log->ip_address,
            'time' => $log->created_at?->format('d M Y, h:i A'),
        ]);

        return ApiResponse::success($rows);
    }
}
