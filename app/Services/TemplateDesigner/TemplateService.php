<?php

namespace App\Services\TemplateDesigner;

use App\Models\Template;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TemplateService
{
    /** @return list<array<string, mixed>> */
    public function list(array $filters = []): array
    {
        $tenant = Tenant::query()->first();

        return Template::query()
            ->with('category:id,name,slug')
            ->when($tenant, fn ($q) => $q->where('tenant_id', $tenant->id))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Template $t) => $this->row($t))
            ->all();
    }

    public function create(array $data, ?int $userId = null): Template
    {
        $tenant = Tenant::query()->first();

        return Template::create([
            'tenant_id' => $tenant?->id,
            'category_id' => $data['category_id'],
            'name' => $data['name'],
            'slug' => Str::slug($data['slug'] ?? $data['name']),
            'description' => $data['description'] ?? null,
            'paper_size' => $data['paper_size'] ?? 'a4_portrait',
            'orientation' => $data['orientation'] ?? 'portrait',
            'background_image' => $data['background_image'] ?? null,
            'canvas_json' => $data['canvas_json'] ?? CanvasDefaults::empty($data['paper_size'] ?? 'a4_portrait'),
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function update(Template $template, array $data, ?int $userId = null): Template
    {
        $template->update([
            ...collect($data)->only([
                'category_id', 'name', 'description', 'paper_size', 'orientation',
                'background_image', 'canvas_json', 'is_active',
            ])->filter(fn ($v) => $v !== null)->all(),
            'updated_by' => $userId,
        ]);

        if (! empty($data['slug'])) {
            $template->update(['slug' => Str::slug($data['slug'])]);
        }

        return $template->fresh(['category']);
    }

    /** @return array<string, mixed> */
    public function row(Template $t): array
    {
        $t->loadMissing('category');

        return [
            'id' => $t->id,
            'name' => $t->name,
            'slug' => $t->slug,
            'description' => $t->description,
            'category_id' => $t->category_id,
            'category' => $t->category ? ['id' => $t->category->id, 'name' => $t->category->name, 'slug' => $t->category->slug] : null,
            'paper_size' => $t->paper_size,
            'orientation' => $t->orientation,
            'background_image' => $t->background_image,
            'background_url' => $t->background_image ? '/storage/'.ltrim(str_replace('\\', '/', $t->background_image), '/') : null,
            'is_active' => $t->is_active,
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Template $t): array
    {
        return [
            ...$this->row($t),
            'canvas_json' => $t->canvas_json,
        ];
    }
}
