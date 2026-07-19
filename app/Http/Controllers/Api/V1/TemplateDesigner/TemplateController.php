<?php

namespace App\Http\Controllers\Api\V1\TemplateDesigner;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ExamResult;
use App\Models\IdCard;
use App\Models\Template;
use App\Services\TemplateDesigner\TemplatePdfService;
use App\Services\TemplateDesigner\TemplateRenderService;
use App\Services\TemplateDesigner\TemplateService;
use App\Services\TemplateDesigner\VariableResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateService $templates,
        private readonly TemplateRenderService $renderer,
        private readonly TemplatePdfService $pdf,
        private readonly VariableResolverService $variables,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->templates->list($request->only(['category_id', 'search', 'is_active'])));
    }

    public function show(Template $template): JsonResponse
    {
        return ApiResponse::success($this->templates->detail($template));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['required', 'exists:template_categories,id'],
            'description' => ['nullable', 'string'],
            'paper_size' => ['nullable', 'in:a4_portrait,a4_landscape'],
            'orientation' => ['nullable', 'in:portrait,landscape'],
            'background_image' => ['nullable', 'string'],
            'canvas_json' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = $this->templates->create($data, $request->user()?->id);

        return ApiResponse::success($this->templates->detail($template), 'Template created', 201);
    }

    public function update(Request $request, Template $template): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'category_id' => ['sometimes', 'exists:template_categories,id'],
            'description' => ['nullable', 'string'],
            'paper_size' => ['nullable', 'in:a4_portrait,a4_landscape'],
            'orientation' => ['nullable', 'in:portrait,landscape'],
            'background_image' => ['nullable', 'string'],
            'canvas_json' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = $this->templates->update($template, $data, $request->user()?->id);

        return ApiResponse::success($this->templates->detail($template), 'Template saved');
    }

    public function destroy(Template $template): JsonResponse
    {
        $template->delete();

        return ApiResponse::success(null, 'Template deleted');
    }

    public function preview(Request $request, Template $template): JsonResponse
    {
        [$student, $examResult] = $this->resolveContext($request);
        $template->loadMissing('category');
        $data = $this->variables->resolve($student, $examResult, $template);

        return ApiResponse::success([
            'html' => $this->renderer->renderHtml($template, $data, forPdf: false),
            'css' => $this->renderer->css(forPdf: false),
            'variables' => $data,
            'student_id' => $student?->id,
            'exam_result_id' => $examResult?->id,
        ]);
    }

    public function generate(Request $request, Template $template)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:id_cards,id'],
            'exam_result_id' => ['nullable', 'exists:exam_results,id'],
        ]);

        $student = IdCard::query()->where('card_type', 'student')->findOrFail($data['student_id']);
        $examResult = ! empty($data['exam_result_id'])
            ? ExamResult::query()->find($data['exam_result_id'])
            : null;

        $pdf = $this->pdf->forStudent($template, $student, $examResult);

        return $pdf->download(Str::slug($template->name).'-'.$student->id.'.pdf');
    }

    /** @return array{0: ?IdCard, 1: ?ExamResult} */
    private function resolveContext(Request $request): array
    {
        $student = null;
        $examResult = null;

        if ($request->filled('student_id')) {
            $student = IdCard::query()
                ->where('card_type', 'student')
                ->find($request->integer('student_id'));
        }

        if ($request->filled('exam_result_id')) {
            $examResult = ExamResult::query()->find($request->integer('exam_result_id'));
        }

        if (! $student) {
            $student = IdCard::query()
                ->where('card_type', 'student')
                ->where('status', 'active')
                ->orderBy('id')
                ->first();
        }

        return [$student, $examResult];
    }
}
