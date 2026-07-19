<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (UserNotification $n) => $this->toRow($n));

        return ApiResponse::success($rows);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success(['count' => $count]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return ApiResponse::error('Forbidden', 403);
        }

        $notification->update(['read_at' => now()]);

        return ApiResponse::success($this->toRow($notification->fresh()));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(null, 'All notifications marked read');
    }

    /** @return array<string, mixed> */
    private function toRow(UserNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'data' => $n->data,
            'read' => $n->read_at !== null,
            'read_at' => $n->read_at?->toIso8601String(),
            'time' => $n->created_at?->diffForHumans() ?? '',
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
