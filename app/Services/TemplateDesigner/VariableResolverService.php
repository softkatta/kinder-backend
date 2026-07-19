<?php

namespace App\Services\TemplateDesigner;

use App\Models\AttendanceRecord;
use App\Models\ExamResult;
use App\Models\IdCard;
use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\TemplateVariable;
use App\Services\IdCard\IdCardService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VariableResolverService
{
    public function __construct(
        private readonly IdCardService $idCards,
    ) {}

    /** @return array<string, string> */
    public function sampleData(
        ?int $studentId = null,
        ?int $examResultId = null,
        ?Template $template = null,
        ?string $categorySlug = null,
    ): array {
        $student = $studentId
            ? IdCard::query()->where('card_type', 'student')->find($studentId)
            : $this->findSampleStudent();

        $examResult = $examResultId
            ? ExamResult::query()->find($examResultId)
            : null;

        $data = $this->resolve($student, $examResult, $template, $categorySlug);

        if (! $student) {
            $data = array_merge($this->demoFallbacks(), array_filter($data, fn ($v) => $v !== '' && $v !== null));
        }

        return $data;
    }

    /** @return array<string, string> */
    private function demoFallbacks(): array
    {
        $rows = TemplateVariable::query()
            ->whereNotNull('sample_value')
            ->where('sample_value', '!=', '')
            ->pluck('sample_value', 'key');

        $out = [];
        foreach ($rows as $key => $value) {
            $out[(string) $key] = (string) $value;
        }

        return $out;
    }

    /** @return array<string, string> */
    public function resolve(
        ?IdCard $student = null,
        ?ExamResult $examResult = null,
        ?Template $template = null,
        ?string $categorySlug = null,
    ): array {
        $school = $this->idCards->schoolProfile();
        $meta = $student?->meta ?? [];
        $cardView = $student ? $this->idCards->toCardViewData($student) : null;
        $examResult = $this->findPrimaryExamResult($student, $examResult);
        $allResults = $student ? $this->findExamResultsForStudent($student) : collect();

        $exam = $examResult?->exam;
        $exam?->loadMissing('academicYear');

        $totals = $this->sumExamTotals($allResults);
        $maxMarks = $exam?->max_marks ?? $totals['max'];
        $obtained = $examResult?->marks_obtained ?? $totals['obtained'];
        $pct = $maxMarks > 0
            ? round(((float) $obtained / (float) $maxMarks) * 100, 1)
            : ($totals['max'] > 0 ? round(($totals['obtained'] / $totals['max']) * 100, 1) : 0);

        $className = $this->metaStr($meta, 'class_name', 'class');
        $sectionName = $this->metaStr($meta, 'section_name', 'section');
        $certNumber = $student
            ? 'CERT-'.now()->format('Y').'-'.str_pad((string) $student->id, 4, '0', STR_PAD_LEFT)
            : '';
        $instructor = $this->teacherForClass($className) ?: $this->metaStr($meta, 'class_teacher');

        $attendanceStats = $this->attendanceStats($student);
        $dobFormatted = $this->formatMetaDate($meta['dob'] ?? $meta['date_of_birth'] ?? null);
        $age = $this->calculateAge($meta['dob'] ?? $meta['date_of_birth'] ?? null);
        $schoolLogo = $this->assetUrl($school['logo_path'] ?? null);
        $schoolSeal = $this->assetUrl($school['school_seal'] ?? 'templates/assets/certified-seal.png');
        $studentPhoto = (string) ($cardView['photo_url'] ?? $this->assetUrl($student?->photo_path));
        $qrCode = (string) ($cardView['qr_data_uri'] ?? '');
        $teacher = $instructor;
        $remarksText = (string) ($examResult?->remarks ?? $this->metaStr($meta, 'remarks', 'teacher_remarks'));

        $data = [
            'student_name' => $student?->full_name ?? '',
            'admission_number' => $this->metaStr($meta, 'admission_number', 'admission_no'),
            'roll_number' => (string) ($examResult?->roll_number ?: $this->metaStr($meta, 'roll_number', 'roll_no')),
            'label_roll_number' => $this->metaStr($meta, 'label_roll_number') ?: 'Roll No.',
            'gr_number' => $this->metaStr($meta, 'gr_number', 'gr_no'),
            'class' => $className,
            'section' => $sectionName,
            'academic_year' => (string) ($student?->academic_year ?? $exam?->academicYear?->name ?? ''),
            'dob' => $dobFormatted,
            'birth_date' => $dobFormatted,
            'age' => $age,
            'gender' => $this->metaStr($meta, 'gender'),
            'nationality' => $this->metaStr($meta, 'nationality') ?: 'Indian',
            'religion' => $this->metaStr($meta, 'religion'),
            'caste' => $this->metaStr($meta, 'caste', 'category'),
            'father_name' => $this->metaStr($meta, 'father_name', 'parent_name'),
            'mother_name' => $this->metaStr($meta, 'mother_name'),
            'address' => $this->metaStr($meta, 'address', 'student_address') ?: (string) ($school['address'] ?? ''),
            'mobile' => $this->metaStr($meta, 'mobile', 'parent_phone', 'phone'),
            'blood_group' => (string) ($student?->blood_group ?? $this->metaStr($meta, 'blood_group')),
            'emergency_contact' => (string) ($student?->emergency_contact ?? $this->metaStr($meta, 'emergency_contact')),
            'card_number' => (string) ($student?->card_number ?? ''),
            'id_issue_date' => $student?->issue_date?->format('d M Y') ?? '',
            'id_expiry_date' => $student?->expiry_date?->format('d M Y') ?? '',
            'school_name' => (string) ($school['name'] ?? ''),
            'school_address' => (string) ($school['address'] ?? ''),
            'school_contact' => $this->schoolContact($school),
            'school_tagline' => (string) ($school['summary'] ?? $this->metaStr($meta, 'school_tagline') ?: 'Nurturing young minds with joy and care'),
            'school_phone' => (string) ($school['phone'] ?? ''),
            'school_email' => (string) ($school['email'] ?? ''),
            'school_website' => (string) ($school['website'] ?? ''),
            'principal_name' => (string) ($school['principal_name'] ?? 'Principal'),
            'teacher_name' => $teacher,
            'instructor_name' => $teacher ?: (string) ($school['principal_name'] ?? ''),
            'udis_number' => (string) ($school['udis_number'] ?? $this->metaStr($meta, 'udis_number', 'udise_number')),
            'generated_date' => now()->format('d-m-Y'),
            'issue_date' => now()->format('d-m-Y'),
            'certificate_number' => $certNumber,
            'school_logo' => $schoolLogo,
            'school_seal' => $schoolSeal,
            'student_photo' => $studentPhoto,
            'qr_code' => $qrCode,
            'certificate_title' => $this->certificateHeading($meta),
            'certificate_subtitle' => $this->certificateSubtitle($examResult, $meta),
            'certify_intro' => $this->metaStr($meta, 'certify_intro') ?: 'This is to Certify that',
            'achievement_description' => $this->metaStr($meta, 'achievement_description')
                ?: $this->buildAchievementDescription($student, $examResult, $meta, $school, $className, $pct),
            'achievement_title' => $this->metaStr($meta, 'achievement_title', 'certificate_title') ?: 'Certificate of Achievement',
            'award_name' => $this->metaStr($meta, 'award_name') ?: 'Excellence Award',
            'event_name' => $this->metaStr($meta, 'event_name') ?: 'Annual Day Celebration',
            'graduation_title' => $this->metaStr($meta, 'graduation_title') ?: 'Certificate of Graduation',
            'completion_message' => $this->metaStr($meta, 'completion_message')
                ?: $this->buildCompletionMessage($student, $meta, $school, $className),
            'promotion_to_class' => $this->metaStr($meta, 'promotion_to_class', 'promoted_to_class') ?: $this->nextClass($className),
            'session' => $this->metaStr($meta, 'session') ?: (string) ($student?->academic_year ?? ''),
            'participation_message' => $this->metaStr($meta, 'participation_message')
                ?: $this->buildParticipationMessage($student, $meta, $school, $className),
            'activity_name' => $this->metaStr($meta, 'activity_name', 'activity') ?: 'School Event',
            'competition_name' => $this->metaStr($meta, 'competition_name', 'competition') ?: 'Inter-School Competition',
            'position' => $this->metaStr($meta, 'position', 'rank') ?: ($examResult ? $this->calculateRank($examResult) : ''),
            'prize' => $this->metaStr($meta, 'prize') ?: 'Gold Medal',
            'event_date' => $this->formatMetaDate($meta['event_date'] ?? null) ?: now()->format('d M Y'),
            'attendance_percentage' => $attendanceStats['pct'],
            'total_working_days' => $attendanceStats['working_days'],
            'days_present' => $attendanceStats['present'],
            'award_message' => $this->metaStr($meta, 'award_message')
                ?: 'for maintaining excellent attendance throughout the academic year.',
            'behaviour_rating' => $this->metaStr($meta, 'behaviour_rating') ?: 'Excellent',
            'teacher_remarks' => $this->metaStr($meta, 'teacher_remarks', 'remarks') ?: $remarksText,
            'appreciation_message' => $this->metaStr($meta, 'appreciation_message')
                ?: 'for exemplary conduct and positive attitude.',
            'activity' => $this->metaStr($meta, 'activity', 'activity_name') ?: 'Art & Craft',
            'competition' => $this->metaStr($meta, 'competition', 'competition_name') ?: 'Creative Arts Competition',
            'award_title' => $this->metaStr($meta, 'award_title') ?: 'Creativity Award',
            'birthday_wishes' => $this->metaStr($meta, 'birthday_wishes')
                ?: 'Wishing you a wonderful birthday filled with joy and happiness!',
            'verification_url' => $this->buildVerificationUrl($certNumber, $school),
            'label_issue_date' => 'Issue Date',
            'label_instructor' => $this->metaStr($meta, 'label_instructor') ?: 'Class Teacher',
            'label_principal' => 'Principal',
            'purpose' => $this->metaStr($meta, 'purpose', 'bonafide_purpose') ?: 'For official use',
            'ref_number' => $student
                ? 'BON-'.now()->format('Y').'-'.str_pad((string) $student->id, 4, '0', STR_PAD_LEFT)
                : '',
            'purpose_text' => $this->metaStr($meta, 'purpose_text', 'bonafide_purpose')
                ?: $this->buildPurposeText($student, $school),
            'lc_number' => $student
                ? 'LC-'.now()->format('Y').'-'.str_pad((string) $student->id, 4, '0', STR_PAD_LEFT)
                : '',
            'admission_date' => $this->formatMetaDate($meta['admission_date'] ?? null),
            'leaving_date' => $this->formatMetaDate($meta['leaving_date'] ?? null) ?: now()->format('d M Y'),
            'last_attendance_date' => $this->lastAttendanceDate($student),
            'last_class' => $this->metaStr($meta, 'last_class') ?: $className,
            'reason_for_leaving' => $this->metaStr($meta, 'reason_for_leaving', 'leaving_reason') ?: 'Transfer',
            'conduct' => $this->metaStr($meta, 'conduct') ?: 'Good',
            'character' => $this->metaStr($meta, 'character') ?: 'Satisfactory',
            'exam_name' => (string) ($exam?->name ?? ''),
            'exam_date' => $exam?->exam_date?->format('d M Y') ?? '',
            'marks_obtained' => (string) ($examResult?->marks_obtained ?? $totals['obtained']),
            'max_marks' => (string) ($maxMarks ?: $totals['max']),
            'total_obtained' => (string) $totals['obtained'],
            'total_maximum' => (string) $totals['max'],
            'percentage' => $pct.'%',
            'grade' => (string) ($examResult?->grade ?? $this->gradeFromPercentage($pct)),
            'result' => strtoupper((string) ($examResult?->result_status ?? $this->metaStr($meta, 'result') ?: 'PASS')),
            'rank' => $examResult ? $this->calculateRank($examResult) : $this->metaStr($meta, 'rank'),
            'remarks' => $remarksText,
            'principal_signature' => $this->assetUrl($school['principal_signature'] ?? null),
            'marks_table' => $this->marksTableHtml($student, $allResults, $examResult),
            'attendance' => $this->attendanceHtml($student),
        ];

        // Backward-compatible aliases for older templates
        $data['logo'] = $data['school_logo'];
        $data['photo'] = $data['student_photo'];
        $data['attendance_pct'] = $data['attendance_percentage'];
        $data['category_name'] = $this->resolveCategoryName($template, $categorySlug);
        $data['roll_number_labeled'] = $data['roll_number'] !== ''
            ? trim($data['label_roll_number'].' '.$data['roll_number'])
            : '';

        return $data;
    }

    private function resolveCategoryName(?Template $template, ?string $categorySlug): string
    {
        if ($template) {
            $template->loadMissing('category');
            $name = $this->formatCategoryName($template->category?->name);

            if ($name !== '') {
                return $name;
            }
        }

        if ($categorySlug) {
            $category = TemplateCategory::query()->where('slug', $categorySlug)->first();

            return $this->formatCategoryName($category?->name);
        }

        return '';
    }

    private function formatCategoryName(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        $clean = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $name) ?? $name;
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean);
        $clean = preg_replace('/\s+certificate$/iu', '', $clean) ?? $clean;

        return trim($clean);
    }

    private function findSampleStudent(): ?IdCard
    {
        return IdCard::query()
            ->where('card_type', 'student')
            ->where('status', 'active')
            ->orderBy('id')
            ->first();
    }

  /** @return Collection<int, ExamResult> */
    private function findExamResultsForStudent(IdCard $student): Collection
    {
        $meta = $student->meta ?? [];
        $roll = $this->metaStr($meta, 'roll_number', 'roll_no');
        $class = $this->metaStr($meta, 'class_name', 'class');
        $name = $student->full_name;

        return ExamResult::query()
            ->with('exam.academicYear')
            ->where(function ($q) use ($roll, $name, $class) {
                if ($roll !== '') {
                    $q->orWhere('roll_number', $roll);
                }
                if ($name !== '') {
                    $q->orWhere('student_name', $name);
                }
                if ($class !== '') {
                    $q->orWhere('class_name', $class);
                }
            })
            ->latest('updated_at')
            ->get()
            ->filter(function (ExamResult $r) use ($roll, $name) {
                if ($roll !== '' && $r->roll_number === $roll) {
                    return true;
                }

                return strcasecmp($r->student_name, $name) === 0;
            })
            ->values();
    }

    private function findPrimaryExamResult(?IdCard $student, ?ExamResult $override): ?ExamResult
    {
        if ($override) {
            $override->loadMissing('exam.academicYear');

            return $override;
        }
        if (! $student) {
            return null;
        }

        return $this->findExamResultsForStudent($student)->first();
    }

    /** @param Collection<int, ExamResult> $results
     * @return array{obtained: float, max: float}
     */
    private function sumExamTotals(Collection $results): array
    {
        $obtained = 0.0;
        $max = 0.0;

        foreach ($results as $result) {
            $result->loadMissing('exam');
            $obtained += (float) $result->marks_obtained;
            $max += (float) ($result->exam?->max_marks ?? 0);
        }

        return ['obtained' => $obtained, 'max' => $max];
    }

    private function teacherForClass(string $className): string
    {
        if ($className === '') {
            return '';
        }

        $teacher = IdCard::query()
            ->where('card_type', 'teacher')
            ->where('status', 'active')
            ->where(function ($q) use ($className) {
                $q->where('meta->assigned_class', $className)
                    ->orWhere('meta->class_name', $className);
            })
            ->orderBy('id')
            ->first();

        return $teacher?->full_name ?? '';
    }

    /** @param array<string, mixed> $meta */
    private function certificateHeading(array $meta): string
    {
        $custom = $this->metaStr($meta, 'certificate_heading', 'certificate_title');

        return $custom !== '' ? strtoupper($custom) : 'CERTIFICATE';
    }

    /** @param array<string, mixed> $meta */
    private function certificateSubtitle(?ExamResult $examResult, array $meta): string
    {
        $custom = $this->metaStr($meta, 'certificate_subtitle');
        if ($custom !== '') {
            return $custom;
        }

        return $examResult?->result_status === 'pass' ? 'of Achievement' : 'of Completion';
    }

    /** @param array<string, mixed> $meta
     * @param array<string, mixed> $school
     */
    private function buildAchievementDescription(
        ?IdCard $student,
        ?ExamResult $examResult,
        array $meta,
        array $school,
        string $className,
        float $pct,
    ): string {
        $custom = $this->metaStr($meta, 'achievement_description', 'certificate_description');
        if ($custom !== '') {
            return $custom;
        }

        $eventName = $this->metaStr($meta, 'event_name', 'activity_name', 'exam_name')
            ?: (string) ($examResult?->exam?->name ?? 'Annual Examination');
        $schoolName = (string) ($school['name'] ?? 'this institution');
        $year = (string) ($student?->academic_year ?? $this->metaStr($meta, 'session') ?: '');
        $grade = (string) ($examResult?->grade ?? '');
        $remarks = (string) ($examResult?->remarks ?? '');

        $parts = ["has successfully completed {$eventName}"];
        if ($className !== '') {
            $parts[] = "for Class {$className}";
        }
        if ($year !== '') {
            $parts[] = "during the academic year {$year}";
        }
        if ($grade !== '') {
            $parts[] = "with Grade {$grade}";
        } elseif ($pct > 0) {
            $parts[] = 'with '.$pct.'% marks';
        }
        if ($remarks !== '') {
            $parts[] = "— {$remarks}";
        }

        return implode(' ', $parts).' at '.$schoolName.'.';
    }

    /** @param array<string, mixed> $meta
     * @param array<string, mixed> $school
     */
    private function buildCompletionMessage(?IdCard $student, array $meta, array $school, string $className): string
    {
        $eventName = $this->metaStr($meta, 'event_name', 'activity_name') ?: 'Kindergarten';
        $schoolName = (string) ($school['name'] ?? 'this institution');
        $year = (string) ($student?->academic_year ?? $this->metaStr($meta, 'session') ?: '');
        $promotion = $this->metaStr($meta, 'promotion_to_class', 'promoted_to_class') ?: $this->nextClass($className);

        $parts = ["has successfully completed {$eventName}"];
        if ($className !== '') {
            $parts[] = "for Class {$className}";
        }
        if ($year !== '') {
            $parts[] = "during the academic year {$year}";
        }
        if ($promotion !== '') {
            $parts[] = "and is promoted to {$promotion}";
        }

        return implode(' ', $parts).' at '.$schoolName.'.';
    }

    /** @param array<string, mixed> $meta
     * @param array<string, mixed> $school
     */
    private function buildParticipationMessage(?IdCard $student, array $meta, array $school, string $className): string
    {
        $eventName = $this->metaStr($meta, 'event_name', 'activity_name') ?: 'the school event';
        $schoolName = (string) ($school['name'] ?? 'this institution');
        $year = (string) ($student?->academic_year ?? $this->metaStr($meta, 'session') ?: '');

        $parts = ["has actively participated in {$eventName}"];
        if ($className !== '') {
            $parts[] = "for Class {$className}";
        }
        if ($year !== '') {
            $parts[] = "during the academic year {$year}";
        }

        return implode(' ', $parts).' at '.$schoolName.' with enthusiasm and team spirit.';
    }

    /** @param array<string, mixed> $school */
    private function buildVerificationUrl(string $certNumber, array $school): string
    {
        if ($certNumber === '') {
            return '';
        }

        $base = rtrim((string) config('template_designer.frontend_url', config('app.url')), '/');

        return "Certificate Verification: {$base}/verify/{$certNumber}";
    }

    /** @param array<string, mixed> $school */
    private function buildPurposeText(?IdCard $student, array $school): string
    {
        if (! $student) {
            return '';
        }

        $schoolName = $school['name'] ?? 'this institution';
        $year = $student->academic_year ?? now()->format('Y');

        return "This is to certify that {$student->full_name} is a bonafide student of {$schoolName} for the academic year {$year}.";
    }

    private function calculateRank(ExamResult $result): string
    {
        $result->loadMissing('exam');
        $examId = $result->exam_id;
        if (! $examId) {
            return '';
        }

        $ranked = ExamResult::query()
            ->where('exam_id', $examId)
            ->orderByDesc('marks_obtained')
            ->pluck('id')
            ->values();

        $pos = $ranked->search($result->id);
        if ($pos === false) {
            return '';
        }

        return $this->ordinal((int) $pos + 1);
    }

    private function ordinal(int $n): string
    {
        $suffix = match ($n % 10) {
            1 => $n % 100 === 11 ? 'th' : 'st',
            2 => $n % 100 === 12 ? 'th' : 'nd',
            3 => $n % 100 === 13 ? 'th' : 'rd',
            default => 'th',
        };

        return $n.$suffix;
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

    /** @param array<string, mixed> $meta */
    private function metaStr(array $meta, string ...$keys): string
    {
        foreach ($keys as $key) {
            $val = $meta[$key] ?? null;
            if ($val !== null && $val !== '') {
                return (string) $val;
            }
        }

        return '';
    }

    private function formatMetaDate(mixed $value): string
    {
        if (! $value) {
            return '';
        }
        try {
            return Carbon::parse((string) $value)->format('d M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function assetUrl(?string $path): string
    {
        if (! $path) {
            return '';
        }
        if (str_starts_with($path, 'data:')) {
            return $path;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            if (is_string($parsedPath) && str_starts_with($parsedPath, '/storage/')) {
                return $parsedPath;
            }

            return $path;
        }

        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }

    /** @param Collection<int, ExamResult> $results */
    private function marksTableHtml(?IdCard $student, Collection $results, ?ExamResult $primary): string
    {
        if ($results->isEmpty()) {
            return $primary
                ? $this->singleResultTable($primary)
                : '<table class="td-table"><tr><th>Subject</th><th>Max</th><th>Obtained</th><th>Grade</th></tr><tr><td colspan="4" style="text-align:center;color:#64748b;">No exam results found</td></tr></table>';
        }

        $rows = '';
        foreach ($results as $result) {
            $result->loadMissing('exam');
            $exam = $result->exam;
            $rows .= '<tr><td>'.e($exam?->subject ?? $exam?->name ?? 'Exam').'</td>'
                .'<td>'.e((string) ($exam?->max_marks ?? '')).'</td>'
                .'<td>'.e((string) $result->marks_obtained).'</td>'
                .'<td>'.e($result->grade ?? '').'</td></tr>';
        }

        $totals = $this->sumExamTotals($results);
        $pct = $totals['max'] > 0 ? round(($totals['obtained'] / $totals['max']) * 100, 1) : 0;

        return '<table class="td-table"><tr><th>Subject</th><th>Max</th><th>Obtained</th><th>Grade</th></tr>'
            .$rows
            .'<tr><td colspan="2"><strong>Total</strong></td><td><strong>'.e((string) $totals['obtained']).'</strong></td><td><strong>'.e((string) $pct).'%</strong></td></tr></table>';
    }

    private function singleResultTable(ExamResult $result): string
    {
        $result->loadMissing('exam');
        $exam = $result->exam;
        $pct = $exam && $exam->max_marks > 0
            ? round(($result->marks_obtained / $exam->max_marks) * 100, 1)
            : 0;

        return '<table class="td-table"><tr><th>Subject</th><th>Max</th><th>Obtained</th><th>Grade</th></tr>'
            .'<tr><td>'.e($exam?->subject ?? $exam?->name ?? 'Exam').'</td><td>'.e((string) ($exam?->max_marks ?? '')).'</td><td>'.e((string) $result->marks_obtained).'</td><td>'.e($result->grade ?? '').'</td></tr>'
            .'<tr><td colspan="2"><strong>Percentage</strong></td><td colspan="2">'.e((string) $pct).'%</td></tr></table>';
    }

    /** @param array<string, mixed> $school */
    private function schoolContact(array $school): string
    {
        return trim(implode(' | ', array_filter([
            (string) ($school['phone'] ?? ''),
            (string) ($school['email'] ?? ''),
        ])));
    }

    private function calculateAge(mixed $dob): string
    {
        if (! $dob) {
            return '';
        }
        try {
            $years = (int) Carbon::parse((string) $dob)->diffInYears(now());

            return $years > 0 ? $years.' years' : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function nextClass(string $className): string
    {
        $map = [
            'nursery' => 'LKG',
            'lkg' => 'UKG',
            'ukg' => 'Class 1',
            'kg' => 'Class 1',
        ];

        $key = strtolower(trim($className));

        return $map[$key] ?? '';
    }

    private function lastAttendanceDate(?IdCard $student): string
    {
        if (! $student) {
            return '';
        }

        $last = AttendanceRecord::query()
            ->where('id_card_id', $student->id)
            ->where('status', 'present')
            ->orderByDesc('date')
            ->first();

        return $last?->date?->format('d M Y') ?? '';
    }

    /** @return array{pct: string, working_days: string, present: string} */
    private function attendanceStats(?IdCard $student): array
    {
        if (! $student) {
            return ['pct' => '', 'working_days' => '', 'present' => ''];
        }

        $records = AttendanceRecord::query()
            ->where('id_card_id', $student->id)
            ->where('date', '>=', now()->subYear()->startOfMonth())
            ->get();

        if ($records->isEmpty()) {
            return ['pct' => '', 'working_days' => '', 'present' => ''];
        }

        $present = $records->where('status', 'present')->count();
        $total = $records->whereIn('status', ['present', 'absent'])->count();
        $pct = $total > 0 ? round(($present / $total) * 100).'%' : '';

        return [
            'pct' => $pct,
            'working_days' => (string) $total,
            'present' => (string) $present,
        ];
    }

    private function attendancePercentage(?IdCard $student): string
    {
        return $this->attendanceStats($student)['pct'];
    }

    private function attendanceHtml(?IdCard $student): string
    {
        if (! $student) {
            return '<table class="td-table"><tr><th>Month</th><th>Present</th><th>Absent</th><th>%</th></tr><tr><td colspan="4" style="text-align:center;color:#64748b;">No attendance data</td></tr></table>';
        }

        $records = AttendanceRecord::query()
            ->where('id_card_id', $student->id)
            ->where('date', '>=', now()->subMonths(6)->startOfMonth())
            ->orderBy('date')
            ->get();

        if ($records->isEmpty()) {
            return '<table class="td-table"><tr><th>Month</th><th>Present</th><th>Absent</th><th>%</th></tr><tr><td colspan="4" style="text-align:center;color:#64748b;">No attendance records</td></tr></table>';
        }

        $byMonth = $records->groupBy(fn (AttendanceRecord $r) => $r->date->format('M Y'));
        $rows = '';
        foreach ($byMonth as $month => $monthRecords) {
            $present = $monthRecords->where('status', 'present')->count();
            $absent = $monthRecords->where('status', 'absent')->count();
            $total = $present + $absent;
            $pct = $total > 0 ? round(($present / $total) * 100) : 0;
            $rows .= '<tr><td>'.e($month).'</td><td>'.e((string) $present).'</td><td>'.e((string) $absent).'</td><td>'.e((string) $pct).'%</td></tr>';
        }

        return '<table class="td-table"><tr><th>Month</th><th>Present</th><th>Absent</th><th>%</th></tr>'.$rows.'</table>';
    }
}
