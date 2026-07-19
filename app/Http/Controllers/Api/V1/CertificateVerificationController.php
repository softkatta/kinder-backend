<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ExamResult;
use App\Models\IdCard;
use App\Services\IdCard\IdCardService;
use Illuminate\Http\JsonResponse;

class CertificateVerificationController extends Controller
{
    public function __construct(
        private readonly IdCardService $idCards,
    ) {}

    public function show(string $certNumber): JsonResponse
    {
        $studentId = $this->parseStudentId($certNumber);
        if ($studentId === null) {
            return ApiResponse::error('Invalid certificate number format.', 404);
        }

        $student = IdCard::query()
            ->where('card_type', 'student')
            ->where('id', $studentId)
            ->first();

        if (! $student) {
            return ApiResponse::error('Certificate not found.', 404);
        }

        $meta = $student->meta ?? [];
        $school = $this->idCards->schoolProfile();
        $className = (string) ($meta['class_name'] ?? $meta['class'] ?? '');
        $roll = (string) ($meta['roll_number'] ?? $meta['roll_no'] ?? '');

        $examResult = ExamResult::query()
            ->with('exam.academicYear')
            ->where('student_name', $student->full_name)
            ->when($roll !== '', fn ($q) => $q->where('roll_number', $roll))
            ->latest('updated_at')
            ->first();

        return ApiResponse::success([
            'valid' => true,
            'certificate_number' => $certNumber,
            'student_name' => $student->full_name,
            'class_name' => $className,
            'roll_number' => $roll,
            'academic_year' => (string) ($student->academic_year ?? $examResult?->exam?->academicYear?->name ?? ''),
            'school_name' => (string) ($school['name'] ?? ''),
            'school_address' => (string) ($school['address'] ?? ''),
            'status' => $student->status,
            'exam_name' => $examResult?->exam?->name,
            'grade' => $examResult?->grade,
            'result_status' => $examResult?->result_status,
            'verified_at' => now()->toIso8601String(),
        ]);
    }

    private function parseStudentId(string $certNumber): ?int
    {
        if (preg_match('/^CERT-\d{4}-(\d+)$/i', strtoupper(trim($certNumber)), $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
