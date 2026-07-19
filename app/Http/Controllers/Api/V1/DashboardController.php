<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function admin(Request $request): JsonResponse
    {
        $data = $this->dashboard->admin();
        $data['greeting'] = 'Welcome, '.$request->user()->name;

        return ApiResponse::success($data);
    }

    public function teacher(Request $request): JsonResponse
    {
        return ApiResponse::success($this->dashboard->teacher($request->user()));
    }

    public function parent(Request $request): JsonResponse
    {
        return ApiResponse::success($this->dashboard->parent($request->user()));
    }

    public function student(Request $request): JsonResponse
    {
        return ApiResponse::success($this->dashboard->student($request->user()));
    }

    public function guest(Request $request): JsonResponse
    {
        $guest = $request->user()->guestPass;
        if (! $guest) {
            return ApiResponse::error('Guest pass not found', 404);
        }

        $guest->load(['companions', 'entryLogs' => fn ($q) => $q->limit(10)]);

        return ApiResponse::success([
            'greeting' => 'Welcome, '.$guest->full_name,
            'event' => [
                'name' => $guest->event_name,
                'date' => $guest->event_date?->format('d M Y'),
                'location' => $guest->event_location,
                'valid_from' => $guest->valid_from->format('d M Y'),
                'valid_until' => $guest->valid_until->format('d M Y'),
            ],
            'stats' => [
                ['label' => 'Companions', 'value' => $guest->companions->count(), 'change' => 'Coming with you'],
                ['label' => 'Guest Code', 'value' => $guest->guest_code, 'change' => 'Show at entry'],
                ['label' => 'Pass Status', 'value' => ucfirst($guest->status), 'change' => $guest->isScannable() ? 'Valid' : 'Check dates'],
                ['label' => 'Entry Logs', 'value' => $guest->entryLogs->count(), 'change' => 'Recent scans'],
            ],
            'companions' => $guest->companions->map(fn ($c) => [
                'name' => $c->full_name,
                'relation' => $c->relation,
                'phone' => $c->phone,
            ])->values()->all(),
        ]);
    }

    public function cmsSummary(): JsonResponse
    {
        return ApiResponse::success($this->dashboard->cmsTypeSummary());
    }

    public function adminSidebar(): JsonResponse
    {
        return ApiResponse::success($this->dashboard->adminSidebar());
    }
}
