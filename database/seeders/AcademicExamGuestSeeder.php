<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Guest;
use App\Models\Tenant;
use App\Services\Guest\GuestService;
use Illuminate\Database\Seeder;

class AcademicExamGuestSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->first();
        $guestService = app(GuestService::class);

        $year = AcademicYear::query()->updateOrCreate(
            ['tenant_id' => $tenant?->id, 'name' => '2026-2027'],
            [
                'label' => 'Academic Year 2026–2027',
                'start_date' => '2026-06-01',
                'end_date' => '2027-04-30',
                'is_current' => true,
                'status' => 'active',
            ],
        );

        $exam = Exam::query()->updateOrCreate(
            ['tenant_id' => $tenant?->id, 'name' => 'Annual Examination', 'class_name' => 'Nursery'],
            [
                'academic_year_id' => $year->id,
                'exam_type' => 'annual',
                'subject' => 'All Subjects',
                'exam_date' => '2026-03-15',
                'max_marks' => 100,
                'status' => 'completed',
            ],
        );

        $lkgExam = Exam::query()->updateOrCreate(
            ['tenant_id' => $tenant?->id, 'name' => 'Annual Examination', 'class_name' => 'LKG'],
            [
                'academic_year_id' => $year->id,
                'exam_type' => 'annual',
                'subject' => 'All Subjects',
                'exam_date' => '2026-03-15',
                'max_marks' => 100,
                'status' => 'completed',
            ],
        );

        foreach ([
            [$exam->id, 'Aarav Patel', '12', 'Nursery', 88, 'A+', 'pass'],
            [$lkgExam->id, 'Ananya Sharma', '08', 'LKG', 76, 'B+', 'pass'],
        ] as [$examId, $name, $roll, $class, $marks, $grade, $status]) {
            ExamResult::query()->updateOrCreate(
                ['exam_id' => $examId, 'roll_number' => $roll],
                [
                    'tenant_id' => $tenant?->id,
                    'student_name' => $name,
                    'class_name' => $class,
                    'marks_obtained' => $marks,
                    'grade' => $grade,
                    'result_status' => $status,
                    'remarks' => 'Excellent performance',
                ],
            );
        }

        $guest = Guest::query()->updateOrCreate(
            ['tenant_id' => $tenant?->id, 'qr_token' => 'LS-GUEST-DEMO001'],
            [
                'guest_code' => $guestService->generateGuestCode(),
                'scan_code' => 'demoguestscan01',
                'full_name' => 'Rahul Deshmukh',
                'phone' => '+91 98765 11111',
                'email' => 'guest@littlestars.com',
                'event_name' => 'Annual Day Celebration',
                'event_date' => now()->addDays(7)->toDateString(),
                'event_location' => 'School Auditorium',
                'valid_from' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'status' => 'active',
                'notes' => 'VIP parent guest — demo QR: LS-GUEST-DEMO001',
            ],
        );

        $guestService->syncPortalUser($guest->fresh());

        if ($guest->companions()->count() === 0) {
            $guest->companions()->createMany([
                [
                    'full_name' => 'Priya Deshmukh',
                    'phone' => '+91 98765 22222',
                    'relation' => 'Spouse',
                    'can_entry' => true,
                    'sort_order' => 0,
                ],
                [
                    'full_name' => 'Arjun Deshmukh',
                    'phone' => null,
                    'relation' => 'Child',
                    'can_entry' => true,
                    'sort_order' => 1,
                ],
            ]);
        }
    }
}
