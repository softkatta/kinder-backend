<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\FeeCategory;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $items = FeeCategory::query()->orderBy('name')->get();

        return ApiResponse::success($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $tenant = Tenant::query()->first();

        $item = FeeCategory::create([...$data, 'tenant_id' => $tenant?->id]);

        return ApiResponse::success($item, 'Fee category created', 201);
    }

    public function update(Request $request, FeeCategory $feeCategory): JsonResponse
    {
        $feeCategory->update($this->validated($request));

        return ApiResponse::success($feeCategory->fresh(), 'Updated');
    }

    public function destroy(FeeCategory $feeCategory): JsonResponse
    {
        $feeCategory->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0'],
            'frequency' => ['nullable', 'string', Rule::in(['monthly', 'quarterly', 'yearly', 'one_time'])],
            'grade_level' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
