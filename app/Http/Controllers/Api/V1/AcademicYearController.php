<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AcademicYear;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicYearController extends Controller
{
    public function index(): JsonResponse
    {
        $years = AcademicYear::query()
            ->withCount('exams')
            ->orderByDesc('start_date')
            ->get();

        return ApiResponse::success($years);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $tenant = Tenant::query()->first();

        if (! empty($data['is_current'])) {
            AcademicYear::query()->update(['is_current' => false]);
        }

        $year = AcademicYear::create([
            ...$data,
            'tenant_id' => $tenant?->id,
        ]);

        return ApiResponse::success($year, 'Academic year created', 201);
    }

    public function show(AcademicYear $academicYear): JsonResponse
    {
        return ApiResponse::success($academicYear);
    }

    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $data = $this->validated($request, $academicYear->id);

        if (! empty($data['is_current'])) {
            AcademicYear::query()->where('id', '!=', $academicYear->id)->update(['is_current' => false]);
        }

        $academicYear->update($data);

        return ApiResponse::success($academicYear->fresh(), 'Updated');
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        $examCount = $academicYear->exams()->count();
        $wasCurrent = $academicYear->is_current;

        $academicYear->delete();

        if ($wasCurrent) {
            $next = AcademicYear::query()->orderByDesc('start_date')->first();
            if ($next) {
                AcademicYear::query()->update(['is_current' => false]);
                $next->update(['is_current' => true]);
            }
        }

        $message = $examCount > 0
            ? "Academic year deleted ({$examCount} linked exam(s) removed)"
            : 'Deleted';

        return ApiResponse::success(null, $message);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $tenantId = Tenant::query()->value('id');

        return $request->validate([
            'name' => [
                'required', 'string', 'max:20',
                Rule::unique('academic_years', 'name')->where('tenant_id', $tenantId)->ignore($ignoreId),
            ],
            'label' => ['nullable', 'string', 'max:80'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_current' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);
    }
}
