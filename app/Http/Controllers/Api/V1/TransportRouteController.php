<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\IdCard;
use App\Models\Tenant;
use App\Models\TransportRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransportRouteController extends Controller
{
    public function index(): JsonResponse
    {
        $routes = TransportRoute::query()
            ->withCount(['students' => fn ($q) => $q->where('card_type', 'student')])
            ->orderBy('name')
            ->get()
            ->map(fn (TransportRoute $route) => $this->toRow($route));

        return ApiResponse::success($routes);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $tenant = Tenant::query()->first();

        $route = TransportRoute::create([...$data, 'tenant_id' => $tenant?->id]);

        return ApiResponse::success($this->toRow($route), 'Route created', 201);
    }

    public function update(Request $request, TransportRoute $transportRoute): JsonResponse
    {
        $transportRoute->update($this->validated($request));

        return ApiResponse::success($this->toRow($transportRoute->fresh()), 'Updated');
    }

    public function destroy(TransportRoute $transportRoute): JsonResponse
    {
        IdCard::query()->where('transport_route_id', $transportRoute->id)->update(['transport_route_id' => null]);
        $transportRoute->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    public function assignStudent(Request $request, IdCard $student): JsonResponse
    {
        if ($student->card_type !== 'student') {
            return ApiResponse::error('Student not found', 404);
        }

        $data = $request->validate([
            'transport_route_id' => ['nullable', 'exists:transport_routes,id'],
        ]);

        $student->update(['transport_route_id' => $data['transport_route_id'] ?? null]);

        return ApiResponse::success([
            'id' => $student->id,
            'full_name' => $student->full_name,
            'transport_route_id' => $student->transport_route_id,
            'transport_route' => $student->fresh()->transportRoute?->only(['id', 'name', 'area', 'monthly_fee']),
        ], 'Transport updated');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'pickup_points' => ['nullable', 'string', 'max:500'],
            'driver_name' => ['nullable', 'string', 'max:120'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);
    }

    /** @return array<string, mixed> */
    private function toRow(TransportRoute $route): array
    {
        return [
            'id' => $route->id,
            'name' => $route->name,
            'area' => $route->area,
            'pickup_points' => $route->pickup_points,
            'driver_name' => $route->driver_name,
            'driver_phone' => $route->driver_phone,
            'vehicle_number' => $route->vehicle_number,
            'monthly_fee' => (float) $route->monthly_fee,
            'status' => $route->status,
            'students_count' => $route->students_count ?? $route->students()->where('card_type', 'student')->count(),
        ];
    }
}
