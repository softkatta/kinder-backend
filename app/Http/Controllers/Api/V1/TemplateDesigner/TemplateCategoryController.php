<?php

namespace App\Http\Controllers\Api\V1\TemplateDesigner;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\TemplateCategory;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class TemplateCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $tenant = Tenant::query()->first();

        $rows = TemplateCategory::query()
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (TemplateCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                ...$this->defaultBackgroundFor($c->slug),
            ]);

        return ApiResponse::success($rows);
    }

    /** @return array{default_background_image: ?string, default_background_url: ?string} */
    private function defaultBackgroundFor(string $slug): array
    {
        $path = config("template_designer.default_backgrounds.{$slug}");
        if (! $path || ! is_file(public_path('storage/'.ltrim($path, '/')))) {
            return ['default_background_image' => null, 'default_background_url' => null];
        }

        return [
            'default_background_image' => $path,
            'default_background_url' => '/storage/'.ltrim($path, '/'),
        ];
    }
}
