<?php

namespace App\Http\Controllers\Api\V1\TemplateDesigner;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Services\TemplateDesigner\VariableResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateVariableController extends Controller
{
    public function __construct(
        private readonly VariableResolverService $resolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $categorySlug = $request->string('category')->toString();

        $rows = TemplateVariable::query()
            ->orderBy('sort_order')
            ->get()
            ->filter(function (TemplateVariable $v) use ($categorySlug) {
                if ($categorySlug === '') {
                    return true;
                }
                $applies = $v->applies_to ?? [];

                return $applies === [] || in_array($categorySlug, $applies, true);
            })
            ->values()
            ->map(fn (TemplateVariable $v) => [
                'id' => $v->id,
                'key' => $v->key,
                'label' => $v->label,
                'group' => $v->group,
                'data_type' => $v->data_type,
                'applies_to' => $v->applies_to ?? [],
                'tag' => '{{'.$v->key.'}}',
                'sample_value' => $v->sample_value,
            ]);

        return ApiResponse::success($rows);
    }

    public function sample(Request $request): JsonResponse
    {
        $studentId = $request->filled('student_id') ? $request->integer('student_id') : null;
        $examResultId = $request->filled('exam_result_id') ? $request->integer('exam_result_id') : null;
        $template = $request->filled('template_id')
            ? Template::query()->with('category')->find($request->integer('template_id'))
            : null;
        $categorySlug = $request->string('category')->toString() ?: null;

        return ApiResponse::success($this->resolver->sampleData($studentId, $examResultId, $template, $categorySlug));
    }
}
