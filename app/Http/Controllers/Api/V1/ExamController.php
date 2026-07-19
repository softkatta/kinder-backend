<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Tenant;
use App\Services\Exam\ExamDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamDocumentService $documents,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Exam::query()->with('academicYear:id,name')->withCount('results')->latest('exam_date');

        if ($yearId = $request->query('academic_year_id')) {
            $query->where('academic_year_id', $yearId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($class = $request->query('class_name')) {
            $query->where('class_name', $class);
        }

        return ApiResponse::success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedExam($request);
        $tenant = Tenant::query()->first();

        $exam = Exam::create([...$data, 'tenant_id' => $tenant?->id]);

        return ApiResponse::success($exam->load('academicYear'), 'Exam created', 201);
    }

    public function show(Exam $exam): JsonResponse
    {
        return ApiResponse::success($exam->load(['academicYear', 'results']));
    }

    public function update(Request $request, Exam $exam): JsonResponse
    {
        $exam->update($this->validatedExam($request));

        return ApiResponse::success($exam->fresh()->load('academicYear'), 'Updated');
    }

    public function destroy(Exam $exam): JsonResponse
    {
        $exam->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    public function results(Exam $exam): JsonResponse
    {
        return ApiResponse::success($exam->results()->orderBy('student_name')->get());
    }

    public function storeResult(Request $request, Exam $exam): JsonResponse
    {
        $data = $this->validatedResult($request, $exam);
        $tenant = Tenant::query()->first();
        $pct = $exam->max_marks > 0 ? round(($data['marks_obtained'] / $exam->max_marks) * 100, 1) : 0;

        $result = ExamResult::create([
            ...$data,
            'exam_id' => $exam->id,
            'tenant_id' => $tenant?->id,
            'class_name' => $data['class_name'] ?? $exam->class_name,
            'grade' => $data['grade'] ?? null,
            'result_status' => $data['result_status'] ?? ($pct >= 40 ? 'pass' : 'fail'),
        ]);

        if (! $result->grade) {
            $result->update(['grade' => $this->documents->marksheetView($result->fresh())['grade']]);
        }

        return ApiResponse::success($result->load('exam'), 'Result saved', 201);
    }

    public function updateResult(Request $request, ExamResult $examResult): JsonResponse
    {
        $exam = $examResult->exam;
        $examResult->update($this->validatedResult($request, $exam, $examResult->id));

        return ApiResponse::success($examResult->fresh()->load('exam'), 'Updated');
    }

    public function destroyResult(ExamResult $examResult): JsonResponse
    {
        $examResult->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    public function allResults(Request $request): JsonResponse
    {
        $query = ExamResult::query()->with(['exam.academicYear'])->latest('updated_at');

        if ($examId = $request->query('exam_id')) {
            $query->where('exam_id', $examId);
        }
        if ($status = $request->query('result_status')) {
            $query->where('result_status', $status);
        }

        return ApiResponse::success($query->get());
    }

    public function marksheetView(ExamResult $examResult): JsonResponse
    {
        return ApiResponse::success($this->documents->marksheetView($examResult));
    }

    public function certificateView(ExamResult $examResult): JsonResponse
    {
        return ApiResponse::success($this->documents->certificateView($examResult));
    }

    public function markPrinted(Request $request, ExamResult $examResult): JsonResponse
    {
        $type = $request->validate(['type' => ['required', Rule::in(['marksheet', 'certificate'])]])['type'];

        if ($type === 'marksheet') {
            $examResult->update(['marksheet_printed_at' => now()]);
        } else {
            $examResult->update(['certificate_printed_at' => now()]);
        }

        return ApiResponse::success($examResult->fresh());
    }

    private function validatedExam(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:120'],
            'exam_type' => ['sometimes', Rule::in(Exam::TYPES)],
            'class_name' => ['required', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:80'],
            'exam_date' => ['nullable', 'date'],
            'max_marks' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'status' => ['sometimes', Rule::in(Exam::STATUSES)],
            'remarks' => ['nullable', 'string'],
        ]);
    }

    private function validatedResult(Request $request, Exam $exam, ?int $ignoreId = null): array
    {
        return $request->validate([
            'student_name' => ['required', 'string', 'max:120'],
            'roll_number' => ['nullable', 'string', 'max:40'],
            'class_name' => ['nullable', 'string', 'max:40'],
            'marks_obtained' => ['required', 'numeric', 'min:0', 'max:'.$exam->max_marks],
            'grade' => ['nullable', 'string', 'max:10'],
            'result_status' => ['sometimes', Rule::in(['pass', 'fail', 'absent'])],
            'remarks' => ['nullable', 'string'],
        ]);
    }
}
