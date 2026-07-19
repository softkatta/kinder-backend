<?php

namespace App\Services\Exam;

use App\Models\ExamResult;
use App\Models\IdCard;
use App\Models\Template;
use App\Services\IdCard\IdCardService;
use App\Services\TemplateDesigner\TemplateRenderService;
use App\Services\TemplateDesigner\VariableResolverService;

class ExamDocumentService
{
    public function __construct(
        private readonly IdCardService $idCardService,
        private readonly TemplateRenderService $renderer,
        private readonly VariableResolverService $variables,
    ) {}

    public function marksheetView(ExamResult $result): array
    {
        return $this->renderView($result, 'marksheet');
    }

    public function certificateView(ExamResult $result): array
    {
        return $this->renderView($result, 'certificate');
    }

    private function renderView(ExamResult $result, string $type): array
    {
        $template = $this->findTemplate($result, $type);
        if ($template) {
            $student = $this->findStudent($result);
            $data = $this->variables->resolve($student, $result, $template);
            $data = $this->mergeExamResultData($data, $result, $type);

            return [
                'type' => $type,
                'render_mode' => 'template',
                'student_name' => $result->student_name,
                'paper_size' => $template->paper_size,
                'html' => $this->renderer->renderHtml($template, $data, forPdf: false),
                'css' => $this->renderer->css(forPdf: false),
                'grade' => $data['grade'] ?? $result->grade ?? '',
            ];
        }

        return $this->buildLegacyDocumentData($result, $type);
    }

    private function findTemplate(ExamResult $result, string $type): ?Template
    {
        $categorySlug = $this->categorySlugFor($result, $type);
        if ($categorySlug === '') {
            return null;
        }

        $preferredSlug = config("template_designer.exam_templates.{$type}");
        if (is_string($preferredSlug) && $preferredSlug !== '') {
            $preferred = Template::query()
                ->with('category')
                ->where('is_active', true)
                ->where('slug', $preferredSlug)
                ->first();
            if ($preferred) {
                return $preferred;
            }
        }

        return Template::query()
            ->with('category')
            ->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('slug', $categorySlug))
            ->orderByDesc('updated_at')
            ->first();
    }

    private function categorySlugFor(ExamResult $result, string $type): string
    {
        if ($type === 'marksheet') {
            return (string) config('template_designer.exam_categories.marksheet', 'marksheet');
        }

        $map = config('template_designer.exam_categories.certificate', []);

        return (string) ($map[$result->result_status] ?? $map['pass'] ?? 'achievement_certificate');
    }

    private function findStudent(ExamResult $result): ?IdCard
    {
        $query = IdCard::query()
            ->where('card_type', 'student')
            ->where('status', 'active');

        if ($result->roll_number) {
            $byRoll = (clone $query)
                ->where(function ($q) use ($result) {
                    $q->where('meta->roll_number', $result->roll_number)
                        ->orWhere('meta->roll_no', $result->roll_number);
                })
                ->first();
            if ($byRoll) {
                return $byRoll;
            }
        }

        return (clone $query)
            ->where('full_name', $result->student_name)
            ->first();
    }

    /** @param array<string, string> $data */
    private function mergeExamResultData(array $data, ExamResult $result, string $type = 'certificate'): array
    {
        $result->loadMissing('exam.academicYear');
        $exam = $result->exam;
        $percentage = $exam && $exam->max_marks > 0
            ? round(((float) $result->marks_obtained / (float) $exam->max_marks) * 100, 1)
            : 0;

        $overrides = array_filter([
            'student_name' => $result->student_name,
            'roll_number' => (string) ($result->roll_number ?? ''),
            'class' => $result->class_name,
            'exam_name' => $exam?->name,
            'academic_year' => $exam?->academicYear?->name,
            'marks_obtained' => (string) $result->marks_obtained,
            'max_marks' => $exam ? (string) $exam->max_marks : '',
            'percentage' => $percentage.'%',
            'grade' => $result->grade ?? '',
            'result' => strtoupper($result->result_status),
            'remarks' => $result->remarks ?? '',
        ], fn ($v) => $v !== '' && $v !== null);

        if ($type === 'marksheet') {
            $overrides['certificate_title'] = 'MARKSHEET';
        }

        $merged = array_merge($data, $overrides);
        $merged['roll_number_labeled'] = $merged['roll_number'] !== ''
            ? trim(($merged['label_roll_number'] ?? 'Roll No.').' '.$merged['roll_number'])
            : '';

        return $merged;
    }

    private function buildLegacyDocumentData(ExamResult $result, string $type): array
    {
        $result->load('exam.academicYear');
        $exam = $result->exam;
        $school = $this->idCardService->schoolProfile();
        $percentage = $exam->max_marks > 0
            ? round(($result->marks_obtained / $exam->max_marks) * 100, 1)
            : 0;

        $logoPath = $school['logo_path'] ?? null;
        $logoUrl = $logoPath
            ? (str_starts_with($logoPath, 'http') ? $logoPath : asset('storage/'.ltrim($logoPath, '/')))
            : null;

        return [
            'type' => $type,
            'render_mode' => 'legacy',
            'school' => [
                ...$school,
                'logo_url' => $logoUrl,
            ],
            'student_name' => $result->student_name,
            'roll_number' => $result->roll_number,
            'class_name' => $result->class_name,
            'exam_name' => $exam->name,
            'exam_type' => $exam->exam_type,
            'subject' => $exam->subject,
            'exam_date' => $exam->exam_date?->format('d M Y'),
            'academic_year' => $exam->academicYear?->name,
            'marks_obtained' => (float) $result->marks_obtained,
            'max_marks' => $exam->max_marks,
            'percentage' => $percentage,
            'grade' => $result->grade ?? $this->gradeFromPercentage($percentage),
            'result_status' => $result->result_status,
            'remarks' => $result->remarks,
            'issued_date' => now()->format('d M Y'),
            'certificate_title' => $result->result_status === 'pass'
                ? 'Certificate of Achievement'
                : 'Participation Certificate',
        ];
    }

    private function gradeFromPercentage(float $pct): string
    {
        return match (true) {
            $pct >= 90 => 'A+',
            $pct >= 80 => 'A',
            $pct >= 70 => 'B+',
            $pct >= 60 => 'B',
            $pct >= 50 => 'C',
            $pct >= 40 => 'D',
            default => 'F',
        };
    }
}
