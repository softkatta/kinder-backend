<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CmsItem;
use App\Models\JobApplication;
use App\Models\Tenant;
use App\Services\Cms\CmsDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CmsItemController extends Controller
{
    public function __construct(
        private readonly CmsDeletionService $deletion,
    ) {}

    private const TYPES = [
        'program', 'facility', 'activity', 'event', 'blog', 'gallery',
        'faq', 'job', 'page', 'banner', 'testimonial', 'school_profile', 'payment_info',
        'staff', 'curriculum', 'hero', 'notice',
    ];

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $query = CmsItem::query()->orderBy('sort_order')->orderBy('title');

        if ($type) {
            $query->where('type', $type);
        }

        return ApiResponse::success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $tenant = Tenant::query()->first();

        $item = CmsItem::create([
            ...$data,
            'tenant_id' => $tenant?->id,
        ]);

        return ApiResponse::success($item, 'Created', 201);
    }

    public function show(CmsItem $cmsItem): JsonResponse
    {
        return ApiResponse::success($cmsItem);
    }

    public function update(Request $request, CmsItem $cmsItem): JsonResponse
    {
        $cmsItem->update($this->validated($request, $cmsItem->id));

        return ApiResponse::success($cmsItem->fresh(), 'Updated');
    }

    public function destroy(CmsItem $cmsItem): JsonResponse
    {
        $this->deletion->deletePermanently($cmsItem);

        return ApiResponse::success(null, 'Permanently deleted');
    }

    public function jobApplications(): JsonResponse
    {
        $apps = JobApplication::query()
            ->with('job:id,title')
            ->latest()
            ->get();

        return ApiResponse::success($apps);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $tenantId = Tenant::query()->value('id');
        $type = (string) $request->input('type');

        $slug = trim((string) $request->input('slug', ''));
        $slug = $slug !== '' ? Str::slug($slug) : null;

        if ($slug === null && $request->filled('title')) {
            $slug = $this->uniqueSlugForType($type, Str::slug((string) $request->input('title')), $tenantId, $ignoreId);
        }

        $request->merge(['slug' => $slug]);

        return $request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'slug' => [
                'nullable', 'string', 'max:120',
                Rule::unique('cms_items', 'slug')
                    ->where(fn ($q) => $q->where('type', $type)->where('tenant_id', $tenantId))
                    ->ignore($ignoreId),
            ],
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string',
            'body' => 'nullable|string',
            'image' => 'nullable|string|max:500',
            'meta' => 'nullable|array',
            'status' => 'nullable|in:published,draft',
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }

    private function uniqueSlugForType(string $type, string $base, ?int $tenantId, ?int $ignoreId = null): string
    {
        $slug = $base !== '' ? $base : 'item';
        $suffix = 1;

        while ($this->slugExists($type, $slug, $tenantId, $ignoreId)) {
            $slug = ($base !== '' ? $base : 'item').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $type, string $slug, ?int $tenantId, ?int $ignoreId): bool
    {
        return CmsItem::query()
            ->where('type', $type)
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
