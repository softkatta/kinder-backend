<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\TemplateVariable;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TemplateDesignerSeeder extends Seeder
{
    /** @var list<string> */
    private const CERTIFICATE_TYPES = [
        'graduation_certificate',
        'achievement_certificate',
        'participation_certificate',
        'winner_certificate',
        'best_attendance_certificate',
        'good_behaviour_certificate',
        'creativity_award_certificate',
        'birthday_certificate',
    ];

    /** @var list<string> */
    private const OFFICIAL_DOCS = ['bonafide', 'leaving_certificate'];

    /** @var list<string> */
    private const ERP_COMMON_DOCS = [
        'graduation_certificate',
        'achievement_certificate',
        'participation_certificate',
        'winner_certificate',
        'best_attendance_certificate',
        'good_behaviour_certificate',
        'creativity_award_certificate',
        'birthday_certificate',
        'bonafide',
        'leaving_certificate',
    ];

    /** @var list<string> */
    private const ALL_DOCS = [
        ...self::ERP_COMMON_DOCS,
        'marksheet',
        'id_card',
    ];

    public function run(): void
    {
        $this->ensureDefaultBackgrounds();
        $this->seedCategories();
        $this->migrateLegacyCertificateCategory();
        $this->seedVariables();
        $this->seedDefaultCertificateTemplates();
        $this->seedDefaultMarksheetTemplate();
        $this->patchCategoryNameOnTemplates();
        $this->patchRollNumberLabelOnTemplates();
    }

    /** @return list<string> */
    private function certTypes(): array
    {
        return self::CERTIFICATE_TYPES;
    }

    private function ensureDefaultBackgrounds(): void
    {
        $srcDir = resource_path('template-backgrounds');
        $dstDir = storage_path('app/public/templates/backgrounds');
        if (! is_dir($dstDir)) {
            mkdir($dstDir, 0755, true);
        }

        $copied = [];
        foreach (config('template_designer.default_backgrounds', []) as $path) {
            $filename = basename($path);
            if (isset($copied[$filename])) {
                continue;
            }
            $src = $srcDir.'/'.$filename;
            $dst = $dstDir.'/'.$filename;
            if (is_file($src)) {
                copy($src, $dst);
                $copied[$filename] = true;
            }
        }
    }

    private function seedCategories(): void
    {
        $tenant = Tenant::query()->first();
        $categories = [
            'graduation_certificate' => '🎓 Graduation Certificate',
            'achievement_certificate' => '🏅 Achievement Certificate',
            'participation_certificate' => '🎉 Participation Certificate',
            'winner_certificate' => '🥇 Winner Certificate',
            'best_attendance_certificate' => '⭐ Best Attendance Certificate',
            'good_behaviour_certificate' => '😊 Good Behaviour Certificate',
            'creativity_award_certificate' => '🎨 Creativity Award Certificate',
            'birthday_certificate' => '🎂 Birthday Certificate',
            'bonafide' => '📄 Bonafide Certificate',
            'leaving_certificate' => '📄 Leaving Certificate',
            'marksheet' => 'Marksheet',
            'id_card' => 'ID Card',
            'fee_receipt' => 'Fee Receipt',
            'progress_report' => 'Progress Report',
            'custom' => 'Custom',
        ];

        $i = 0;
        foreach ($categories as $slug => $name) {
            TemplateCategory::query()->updateOrCreate(
                ['tenant_id' => $tenant?->id, 'slug' => $slug],
                ['name' => $name, 'sort_order' => $i++, 'is_active' => true]
            );
        }

        TemplateCategory::query()
            ->where('tenant_id', $tenant?->id)
            ->where('slug', 'certificate')
            ->update(['is_active' => false]);
    }

    private function migrateLegacyCertificateCategory(): void
    {
        $tenant = Tenant::query()->first();
        $legacy = TemplateCategory::query()
            ->where('tenant_id', $tenant?->id)
            ->where('slug', 'certificate')
            ->first();
        $achievement = TemplateCategory::query()
            ->where('tenant_id', $tenant?->id)
            ->where('slug', 'achievement_certificate')
            ->first();

        if (! $legacy || ! $achievement) {
            return;
        }

        Template::query()
            ->where('category_id', $legacy->id)
            ->update(['category_id' => $achievement->id]);
    }

    private function seedVariables(): void
    {
        $c = $this->certTypes();
        $erp = self::ERP_COMMON_DOCS;
        $all = self::ALL_DOCS;

        $vars = [
            // —— ERP common dynamic variables ——
            ['school_logo', 'School Logo', 'school', 'image', '', $erp],
            ['school_name', 'School Name', 'school', 'text', 'Little Stars Kindergarten', $all],
            ['school_address', 'School Address', 'school', 'text', '123 Sunshine Lane, Pune', $all],
            ['school_contact', 'School Contact', 'school', 'text', '+91 98765 43210 | info@littlestars.com', array_merge($erp, ['bonafide'])],
            ['student_photo', 'Student Photo', 'student', 'image', '', array_merge($c, ['bonafide', 'leaving_certificate', 'birthday_certificate', 'id_card'])],
            ['student_name', 'Student Name', 'student', 'text', 'Aarav Patel', $all],
            ['admission_number', 'Admission Number', 'student', 'text', 'ADM-2025-001', array_merge($c, ['bonafide', 'leaving_certificate', 'marksheet', 'id_card'])],
            ['gr_number', 'GR Number', 'student', 'text', 'GR-1024', array_merge(['bonafide', 'leaving_certificate'], ['marksheet'])],
            ['roll_number', 'Roll Number', 'student', 'text', '12', array_merge($c, ['marksheet', 'id_card'])],
            ['class', 'Class', 'student', 'text', 'Nursery', $all],
            ['section', 'Section', 'student', 'text', 'A', array_merge($c, ['bonafide', 'leaving_certificate', 'marksheet', 'id_card'])],
            ['academic_year', 'Academic Year', 'student', 'text', '2025-26', $all],
            ['dob', 'Date of Birth', 'student', 'text', '15 Jan 2020', array_merge($c, ['bonafide', 'leaving_certificate', 'marksheet', 'id_card'])],
            ['birth_date', 'Birth Date', 'student', 'text', '15 Jan 2020', ['birthday_certificate']],
            ['age', 'Age', 'student', 'text', '5 years', ['birthday_certificate']],
            ['gender', 'Gender', 'student', 'text', 'Male', ['leaving_certificate']],
            ['father_name', 'Father Name', 'student', 'text', 'Rajesh Patel', array_merge(['bonafide', 'leaving_certificate'], ['marksheet', 'id_card'])],
            ['mother_name', 'Mother Name', 'student', 'text', 'Priya Patel', array_merge(['bonafide', 'leaving_certificate'], ['marksheet'])],
            ['address', 'Student Address', 'student', 'text', '123 Sunshine Lane, Pune', array_merge(['bonafide', 'leaving_certificate'], ['id_card'])],
            ['issue_date', 'Issue Date', 'certificate', 'text', now()->format('d-m-Y'), array_merge($c, self::OFFICIAL_DOCS)],
            ['certificate_number', 'Certificate Number', 'certificate', 'text', 'CERT-2026-0001', $erp],
            ['principal_name', 'Principal Name', 'school', 'text', 'Dr. Meera Patil', $all],
            ['principal_signature', 'Principal Signature', 'school', 'signature', '', $erp],
            ['school_seal', 'School Seal', 'school', 'image', '', array_merge($c, self::OFFICIAL_DOCS)],
            ['qr_code', 'QR Code', 'student', 'image', '', $erp],
            ['teacher_name', 'Teacher Name', 'school', 'text', 'Ms. Sneha Desai', array_merge($c, ['marksheet'])],
            ['remarks', 'Remarks', 'exam', 'text', 'Excellent performance', array_merge($c, ['marksheet', 'creativity_award_certificate', 'good_behaviour_certificate'])],
            ['generated_date', 'Generated Date', 'system', 'text', now()->format('d M Y'), $all],

            // —— 1. Graduation Certificate ——
            ['graduation_title', 'Graduation Title', 'graduation', 'text', 'Certificate of Graduation', ['graduation_certificate']],
            ['completion_message', 'Completion Message', 'graduation', 'text', 'has successfully completed Kindergarten for Class Nursery during the academic year 2025-26 at Little Stars Kindergarten.', ['graduation_certificate']],
            ['promotion_to_class', 'Promotion To Class', 'graduation', 'text', 'LKG', ['graduation_certificate']],
            ['session', 'Session', 'graduation', 'text', '2025-26', ['graduation_certificate']],

            // —— 2. Achievement Certificate ——
            ['achievement_title', 'Achievement Title', 'achievement', 'text', 'Certificate of Achievement', ['achievement_certificate']],
            ['achievement_description', 'Achievement Description', 'achievement', 'text', 'has successfully completed Annual Day Celebration for Class Nursery during the academic year 2025-26 at Little Stars Kindergarten.', ['achievement_certificate']],
            ['award_name', 'Award Name', 'achievement', 'text', 'Excellence Award', ['achievement_certificate']],
            ['event_name', 'Event Name', 'achievement', 'text', 'Annual Day Celebration', array_merge(['achievement_certificate', 'graduation_certificate'], ['participation_certificate', 'winner_certificate'])],
            ['rank', 'Rank', 'achievement', 'text', '1st', ['achievement_certificate']],

            // —— 3. Participation Certificate ——
            ['participation_message', 'Participation Message', 'participation', 'text', 'has actively participated in Annual Day Celebration for Class Nursery during the academic year 2025-26 at Little Stars Kindergarten with enthusiasm and team spirit.', ['participation_certificate']],
            ['activity_name', 'Activity Name', 'participation', 'text', 'Dance Performance', ['participation_certificate']],

            // —— 4. Winner Certificate ——
            ['competition_name', 'Competition Name', 'winner', 'text', 'Inter-School Drawing Competition', ['winner_certificate']],
            ['position', 'Position', 'winner', 'text', '1st', ['winner_certificate']],
            ['prize', 'Prize', 'winner', 'text', 'Gold Medal', ['winner_certificate']],
            ['event_date', 'Event Date', 'winner', 'text', now()->format('d M Y'), ['winner_certificate']],

            // —— 5. Best Attendance Certificate ——
            ['attendance_percentage', 'Attendance Percentage', 'attendance', 'text', '95%', ['best_attendance_certificate']],
            ['total_working_days', 'Total Working Days', 'attendance', 'text', '180', ['best_attendance_certificate']],
            ['days_present', 'Days Present', 'attendance', 'text', '171', ['best_attendance_certificate']],
            ['award_message', 'Award Message', 'attendance', 'text', 'for maintaining excellent attendance throughout the academic year.', ['best_attendance_certificate']],

            // —— 6. Good Behaviour Certificate ——
            ['behaviour_rating', 'Behaviour Rating', 'behaviour', 'text', 'Excellent', ['good_behaviour_certificate']],
            ['teacher_remarks', 'Teacher Remarks', 'behaviour', 'text', 'Shows respect, kindness, and cooperation at all times.', ['good_behaviour_certificate']],
            ['appreciation_message', 'Appreciation Message', 'behaviour', 'text', 'for exemplary conduct and positive attitude.', ['good_behaviour_certificate']],

            // —— 7. Creativity Award Certificate ——
            ['activity', 'Activity', 'creativity', 'text', 'Art & Craft', ['creativity_award_certificate']],
            ['competition', 'Competition', 'creativity', 'text', 'Creative Arts Competition', ['creativity_award_certificate']],
            ['award_title', 'Award Title', 'creativity', 'text', 'Creativity Award', ['creativity_award_certificate']],

            // —— 8. Birthday Certificate ——
            ['birthday_wishes', 'Birthday Wishes', 'birthday', 'text', 'Wishing you a wonderful birthday filled with joy and happiness!', ['birthday_certificate']],

            // —— 9. Bonafide Certificate ——
            ['purpose', 'Purpose', 'bonafide', 'text', 'For school admission / address proof', ['bonafide']],
            ['purpose_text', 'Bonafide Text', 'bonafide', 'text', 'This is to certify that the above student is a bonafide student of this institution.', ['bonafide']],
            ['ref_number', 'Reference Number', 'bonafide', 'text', 'BON-2026-001', ['bonafide']],

            // —— 10. Leaving Certificate ——
            ['leaving_date', 'Date of Leaving', 'leaving', 'text', now()->format('d M Y'), ['leaving_certificate']],
            ['reason_for_leaving', 'Reason for Leaving', 'leaving', 'text', 'Course completed / Transfer', ['leaving_certificate']],
            ['last_attendance_date', 'Last Attendance Date', 'leaving', 'text', now()->format('d M Y'), ['leaving_certificate']],
            ['conduct', 'Conduct', 'leaving', 'text', 'Good', ['leaving_certificate']],
            ['result', 'Result', 'exam', 'text', 'PASS', array_merge(['leaving_certificate'], ['marksheet'])],
            ['lc_number', 'Leaving Certificate Number', 'leaving', 'text', 'LC-2026-001', ['leaving_certificate']],
            ['admission_date', 'Date of Admission', 'student', 'text', '01 Jun 2022', ['leaving_certificate']],
            ['last_class', 'Last Class Studied', 'student', 'text', 'UKG', ['leaving_certificate']],
            ['nationality', 'Nationality', 'student', 'text', 'Indian', ['leaving_certificate']],
            ['religion', 'Religion', 'student', 'text', 'Hindu', ['leaving_certificate']],
            ['caste', 'Caste / Category', 'student', 'text', 'General', ['leaving_certificate']],
            ['udis_number', 'UDISE / School Code', 'school', 'text', '27260100101', ['leaving_certificate']],

            // —— Legacy / layout helpers (achievement-style certificates) ——
            ['category_name', 'Category Name', 'certificate', 'text', 'Achievement', $c],
            ['certificate_title', 'Certificate Heading', 'certificate', 'text', 'CERTIFICATE', $c],
            ['certificate_subtitle', 'Certificate Subtitle', 'certificate', 'text', 'of Achievement', $c],
            ['certify_intro', 'Intro Line', 'certificate', 'text', 'This is to Certify that', $c],
            ['verification_url', 'Verification URL', 'certificate', 'text', 'Certificate Verification: http://localhost:5173/verify/CERT-2026-0001', $c],
            ['school_tagline', 'School Tagline', 'school', 'text', 'Nurturing young minds with joy and care', $c],
            ['label_issue_date', 'Label: Issue Date', 'certificate', 'text', 'Issue Date', $c],
            ['label_roll_number', 'Label: Roll Number', 'certificate', 'text', 'Roll No.', $c],
            ['roll_number_labeled', 'Roll Number with Label', 'student', 'text', 'Roll No. 12', $c],
            ['label_instructor', 'Label: Instructor', 'certificate', 'text', 'Class Teacher', $c],
            ['label_principal', 'Label: Principal', 'certificate', 'text', 'Principal', $c],
            ['instructor_name', 'Instructor Name', 'certificate', 'text', 'Ms. Sneha Desai', $c],
            ['school_phone', 'School Phone', 'school', 'text', '+91 98765 43210', $c],
            ['school_email', 'School Email', 'school', 'text', 'info@littlestars.com', $c],
            ['school_website', 'School Website', 'school', 'text', 'www.littlestars.com', $c],
            ['attendance', 'Attendance Table', 'exam', 'table', '', array_merge($c, ['marksheet', 'progress_report', 'best_attendance_certificate'])],
            ['grade', 'Grade', 'exam', 'text', 'A+', array_merge($c, ['marksheet', 'leaving_certificate'])],
            ['percentage', 'Percentage', 'exam', 'text', '88%', array_merge($c, ['marksheet', 'leaving_certificate'])],
            ['exam_name', 'Exam Name', 'exam', 'text', 'Annual Examination', array_merge($c, ['marksheet'])],

            // —— Marksheet ——
            ['exam_date', 'Exam Date', 'exam', 'text', '15 Mar 2026', ['marksheet']],
            ['marks_obtained', 'Marks Obtained', 'exam', 'text', '88', ['marksheet']],
            ['max_marks', 'Maximum Marks', 'exam', 'text', '100', ['marksheet']],
            ['total_obtained', 'Total Obtained', 'exam', 'text', '440', ['marksheet']],
            ['total_maximum', 'Total Maximum', 'exam', 'text', '500', ['marksheet']],
            ['marks_table', 'Marks Table', 'exam', 'table', '', ['marksheet']],

            // —— ID Card ——
            ['blood_group', 'Blood Group', 'student', 'text', 'B+', ['id_card']],
            ['mobile', 'Mobile Number', 'student', 'text', '+91 98765 43210', ['id_card']],
            ['emergency_contact', 'Emergency Contact', 'student', 'text', '+91 91234 56789', ['id_card']],
            ['card_number', 'ID Card Number', 'student', 'text', 'STU-DEMO001', ['id_card']],
            ['id_issue_date', 'ID Issue Date', 'student', 'text', now()->format('d M Y'), ['id_card']],
            ['id_expiry_date', 'ID Expiry Date', 'student', 'text', now()->addYear()->format('d M Y'), ['id_card']],
        ];

        $keys = array_column($vars, 0);
        TemplateVariable::query()
            ->where('is_system', true)
            ->whereNotIn('key', $keys)
            ->delete();

        foreach ($vars as $i => [$key, $label, $group, $type, $sample, $appliesTo]) {
            TemplateVariable::query()->updateOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'group' => $group,
                    'data_type' => $type,
                    'applies_to' => $appliesTo,
                    'sample_value' => $sample,
                    'is_system' => true,
                    'sort_order' => $i,
                ]
            );
        }
    }

    private function seedDefaultCertificateTemplates(): void
    {
        $tenant = Tenant::query()->first();
        $definitions = [
            'graduation_certificate' => [
                'name' => 'Default Graduation Certificate',
                'message' => 'completion_message',
                'extras' => [
                    $this->textField('promotion_to_class', 70, 142, 55, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('session', 170, 142, 55, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                ],
                'with_photo' => true,
            ],
            'achievement_certificate' => [
                'name' => 'Default Achievement Certificate',
                'message' => 'achievement_description',
                'extras' => [
                    $this->textField('award_name', 70, 142, 70, 7, ['fontSize' => 9, 'bold' => true, 'textAlign' => 'center', 'color' => '#b8860b']),
                    $this->textField('event_name', 155, 142, 70, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                ],
            ],
            'participation_certificate' => [
                'name' => 'Default Participation Certificate',
                'message' => 'participation_message',
                'extras' => [
                    $this->textField('activity_name', 70, 142, 70, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('event_name', 155, 142, 70, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                ],
            ],
            'winner_certificate' => [
                'name' => 'Default Winner Certificate',
                'message' => 'prize',
                'extras' => [
                    $this->textField('position', 55, 142, 40, 7, ['fontSize' => 11, 'bold' => true, 'textAlign' => 'center', 'color' => '#b8860b']),
                    $this->textField('competition_name', 105, 142, 90, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('event_date', 205, 142, 45, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                ],
            ],
            'best_attendance_certificate' => [
                'name' => 'Default Best Attendance Certificate',
                'message' => 'award_message',
                'extras' => [
                    $this->textField('attendance_percentage', 70, 141, 45, 8, ['fontSize' => 12, 'bold' => true, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('days_present', 130, 142, 40, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('total_working_days', 185, 142, 45, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                ],
            ],
            'good_behaviour_certificate' => [
                'name' => 'Default Good Behaviour Certificate',
                'message' => 'appreciation_message',
                'extras' => [
                    $this->textField('behaviour_rating', 70, 142, 55, 7, ['fontSize' => 10, 'bold' => true, 'textAlign' => 'center', 'color' => '#b8860b']),
                    $this->textField('teacher_remarks', 130, 140, 100, 10, ['fontSize' => 8, 'textAlign' => 'center', 'color' => '#475569']),
                ],
            ],
            'creativity_award_certificate' => [
                'name' => 'Default Creativity Award Certificate',
                'message' => 'award_title',
                'extras' => [
                    $this->textField('activity', 55, 142, 55, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('competition', 120, 142, 70, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('teacher_name', 200, 142, 50, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                ],
            ],
            'birthday_certificate' => [
                'name' => 'Default Birthday Certificate',
                'message' => 'birthday_wishes',
                'extras' => [
                    $this->textField('birth_date', 85, 142, 55, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('age', 165, 142, 45, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                ],
                'with_photo' => true,
            ],
            'bonafide' => [
                'name' => 'Default Bonafide Certificate',
                'message' => 'purpose_text',
                'extras' => [
                    $this->textField('admission_number', 55, 142, 50, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('section', 120, 142, 30, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('father_name', 165, 142, 75, 7, ['fontSize' => 8, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('purpose', 50, 150, 197, 8, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#475569']),
                ],
            ],
            'leaving_certificate' => [
                'name' => 'Default Leaving Certificate',
                'message' => 'reason_for_leaving',
                'extras' => [
                    $this->textField('gr_number', 45, 142, 45, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('leaving_date', 100, 142, 45, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('conduct', 155, 142, 35, 7, ['fontSize' => 9, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                    $this->textField('result', 200, 142, 40, 7, ['fontSize' => 9, 'bold' => true, 'textAlign' => 'center', 'color' => '#1e3a5f']),
                ],
            ],
        ];

        foreach ($definitions as $slug => $def) {
            $category = TemplateCategory::query()->where('slug', $slug)->first();
            $bgPath = config("template_designer.default_backgrounds.{$slug}");
            if (! $category || ! $bgPath || ! is_file(public_path('storage/'.ltrim($bgPath, '/')))) {
                continue;
            }

            $objects = $this->landscapeCertificateObjects(
                $def['message'],
                $def['extras'] ?? [],
                (bool) ($def['with_photo'] ?? false),
            );

            Template::query()->updateOrCreate(
                ['tenant_id' => $tenant?->id, 'slug' => 'default-'.$slug],
                [
                    'category_id' => $category->id,
                    'name' => $def['name'],
                    'description' => 'Celebration background (navy banner + gold seal) with fields aligned to the artwork.',
                    'paper_size' => 'a4_landscape',
                    'orientation' => 'landscape',
                    'background_image' => $bgPath,
                    'canvas_json' => [
                        'version' => 2,
                        'settings' => [
                            'width' => 297,
                            'height' => 210,
                            'unit' => 'mm',
                            'gridSize' => 5,
                            'snapToGrid' => true,
                            'showGrid' => false,
                        ],
                        'objects' => $objects,
                    ],
                    'is_active' => true,
                ],
            );
        }

        // Legacy slug kept for older links
        $achievement = TemplateCategory::query()->where('slug', 'achievement_certificate')->first();
        $achievementBg = config('template_designer.default_backgrounds.achievement_certificate');
        if ($achievement && $achievementBg) {
            Template::query()->where('slug', 'certificate-of-completion')->update([
                'category_id' => $achievement->id,
                'background_image' => $achievementBg,
                'name' => 'Certificate of Completion',
            ]);
        }
    }

    private function seedDefaultMarksheetTemplate(): void
    {
        $tenant = Tenant::query()->first();
        $category = TemplateCategory::query()->where('slug', 'marksheet')->first();
        if (! $category) {
            return;
        }

        $objects = [
            $this->imageField('school_logo', 88, 12, 34, 22),
            $this->textField('school_name', 25, 36, 160, 10, ['fontSize' => 16, 'bold' => true, 'textAlign' => 'center', 'color' => '#1e3a5f']),
            $this->textField('certificate_title', 25, 48, 160, 10, ['fontSize' => 14, 'bold' => true, 'textAlign' => 'center', 'color' => '#4f46e5']),
            $this->textField('student_name', 20, 68, 90, 10, ['fontSize' => 13, 'bold' => true]),
            $this->textField('roll_number_labeled', 115, 68, 75, 8, ['fontSize' => 10]),
            $this->textField('class', 20, 78, 45, 8, ['fontSize' => 10]),
            $this->textField('academic_year', 70, 78, 55, 8, ['fontSize' => 10]),
            $this->textField('exam_name', 130, 78, 60, 8, ['fontSize' => 10]),
            $this->textField('marks_table', 20, 92, 170, 90, ['fontSize' => 9]),
            $this->textField('grade', 20, 188, 40, 8, ['fontSize' => 11, 'bold' => true]),
            $this->textField('percentage', 65, 188, 40, 8, ['fontSize' => 11, 'bold' => true]),
            $this->textField('result', 110, 188, 40, 8, ['fontSize' => 11, 'bold' => true]),
            $this->textField('remarks', 20, 198, 170, 12, ['fontSize' => 9, 'color' => '#475569']),
            $this->textField('issue_date', 20, 268, 55, 8, ['fontSize' => 10]),
            $this->textField('principal_name', 130, 268, 60, 8, ['fontSize' => 10, 'textAlign' => 'right']),
        ];

        // Override title for marksheet
        foreach ($objects as &$obj) {
            if (($obj['variableKey'] ?? '') === 'certificate_title') {
                $obj['label'] = 'Marksheet Title';
            }
        }
        unset($obj);

        Template::query()->updateOrCreate(
            ['tenant_id' => $tenant?->id, 'slug' => 'default-marksheet'],
            [
                'category_id' => $category->id,
                'name' => 'Default Marksheet',
                'description' => 'Portrait marksheet with marks table for exam results.',
                'paper_size' => 'a4_portrait',
                'orientation' => 'portrait',
                'background_image' => null,
                'canvas_json' => [
                    'version' => 2,
                    'settings' => [
                        'width' => 210,
                        'height' => 297,
                        'unit' => 'mm',
                        'gridSize' => 5,
                        'snapToGrid' => true,
                        'showGrid' => false,
                    ],
                    'objects' => $objects,
                ],
                'is_active' => true,
            ],
        );
    }

    /**
     * Field layout for celebration-certificate.png (A4 landscape).
     * Zones: white title on navy banner → name above gold divider → body below →
     * signature above printed PRINCIPAL SIGNATURE / DATE lines → seal in laurel.
     *
     * @param  list<array<string, mixed>>  $extras
     * @return list<array<string, mixed>>
     */
    private function landscapeCertificateObjects(string $messageKey, array $extras = [], bool $withPhoto = false): array
    {
        $objects = [
            $this->textField('certificate_number', 18, 11, 52, 6, ['fontSize' => 7, 'color' => '#64748b']),
            $this->imageField('school_logo', 18, 20, 22, 14),
            // Title sits on the navy ribbon (white text).
            $this->textField('certificate_title', 52, 51, 193, 12, [
                'fontSize' => 17,
                'bold' => true,
                'textAlign' => 'center',
                'color' => '#ffffff',
            ]),
            $this->textField('certify_intro', 55, 70, 187, 7, [
                'fontSize' => 10,
                'textAlign' => 'center',
                'color' => '#1e3a5f',
            ]),
            $this->textField('student_name', 45, 82, 207, 14, [
                'fontSize' => 22,
                'bold' => true,
                'textAlign' => 'center',
                'color' => '#b8860b',
            ]),
            $this->textField('roll_number_labeled', 55, 96, 187, 6, [
                'fontSize' => 9,
                'textAlign' => 'center',
                'color' => '#64748b',
            ]),
            // Body text below the printed gold divider.
            $this->textField($messageKey, 48, 112, 201, 22, [
                'fontSize' => 10,
                'textAlign' => 'center',
                'color' => '#1e3a5f',
            ]),
            $this->textField('class', 70, 134, 50, 6, [
                'fontSize' => 9,
                'textAlign' => 'center',
                'color' => '#1e3a5f',
            ]),
            $this->textField('academic_year', 170, 134, 55, 6, [
                'fontSize' => 9,
                'textAlign' => 'center',
                'color' => '#1e3a5f',
            ]),
            // Above printed PRINCIPAL SIGNATURE / DATE labels; seal in laurel wreath.
            $this->imageField('principal_signature', 52, 156, 55, 14),
            $this->textField('principal_name', 50, 170, 60, 6, [
                'fontSize' => 8,
                'textAlign' => 'center',
                'color' => '#1e3a5f',
            ]),
            $this->imageField('school_seal', 132, 152, 30, 30),
            $this->textField('issue_date', 198, 165, 55, 8, [
                'fontSize' => 10,
                'bold' => true,
                'textAlign' => 'center',
                'color' => '#1e3a5f',
            ]),
            // QR below the gold seal ribbons (right side), clear of decorations.
            $this->imageField('qr_code', 252, 58, 18, 18),
        ];

        if ($withPhoto) {
            array_splice($objects, 3, 0, [
                $this->imageField('student_photo', 245, 78, 28, 34),
            ]);
        }

        return array_merge($objects, $extras);
    }

    private function patchCategoryNameOnTemplates(): void
    {
        Template::query()->with('category')->get()->each(function (Template $template) {
            // Celebration artwork layout has title on the navy banner — do not inject category_name.
            $bg = (string) ($template->background_image ?? '');
            if (str_contains($bg, 'celebration-certificate')) {
                return;
            }

            $canvas = $template->canvas_json ?? [];
            $objects = $canvas['objects'] ?? [];
            $keys = array_column($objects, 'variableKey');

            if (! in_array('certificate_title', $keys, true) || in_array('category_name', $keys, true)) {
                return;
            }

            $titleObj = null;
            foreach ($objects as $obj) {
                if (($obj['variableKey'] ?? '') === 'certificate_title') {
                    $titleObj = $obj;
                    break;
                }
            }

            if (! $titleObj) {
                return;
            }

            $objects[] = $this->textField(
                'category_name',
                (float) ($titleObj['x'] ?? 48),
                max(8, (float) ($titleObj['y'] ?? 60) - 12),
                (float) ($titleObj['width'] ?? 80),
                8,
                [
                    'fontSize' => min(14, max(10, (float) ($titleObj['fontSize'] ?? 12) * 0.55)),
                    'bold' => true,
                    'textAlign' => $titleObj['textAlign'] ?? 'center',
                    'color' => '#1e3a5f',
                ],
            );

            $canvas['objects'] = $objects;
            $template->update(['canvas_json' => $canvas]);
        });
    }

    private function patchRollNumberLabelOnTemplates(): void
    {
        Template::query()->get()->each(function (Template $template) {
            $canvas = $template->canvas_json ?? [];
            $objects = $canvas['objects'] ?? [];
            $keys = array_column($objects, 'variableKey');
            $changed = false;

            foreach ($objects as &$obj) {
                if (($obj['variableKey'] ?? '') === 'roll_number') {
                    $obj['variableKey'] = 'roll_number_labeled';
                    $obj['label'] = 'roll_number_labeled';
                    $changed = true;
                }
            }
            unset($obj);

            if (! in_array('roll_number_labeled', $keys, true) && in_array('student_name', $keys, true)) {
                $nameObj = null;
                foreach ($objects as $obj) {
                    if (($obj['variableKey'] ?? '') === 'student_name') {
                        $nameObj = $obj;
                        break;
                    }
                }

                if ($nameObj) {
                    $objects[] = $this->textField(
                        'roll_number_labeled',
                        (float) ($nameObj['x'] ?? 48),
                        (float) ($nameObj['y'] ?? 84) + (float) ($nameObj['height'] ?? 12) + 2,
                        (float) ($nameObj['width'] ?? 80),
                        7,
                        [
                            'fontSize' => 11,
                            'textAlign' => $nameObj['textAlign'] ?? 'center',
                            'color' => '#475569',
                        ],
                    );
                    $changed = true;
                }
            }

            if ($changed) {
                $canvas['objects'] = $objects;
                $template->update(['canvas_json' => $canvas]);
            }
        });
    }

    /** @param array<string, mixed> $style */
    private function textField(string $key, float $x, float $y, float $w, float $h, array $style = []): array
    {
        return [
            'id' => 'fld_seed_'.Str::slug($key),
            'objectType' => 'variable',
            'variableKey' => $key,
            'dataType' => 'text',
            'label' => $key,
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'rotation' => 0,
            'fontFamily' => $style['fontFamily'] ?? 'DejaVu Sans',
            'fontSize' => $style['fontSize'] ?? 12,
            'bold' => $style['bold'] ?? false,
            'italic' => $style['italic'] ?? false,
            'underline' => $style['underline'] ?? false,
            'color' => $style['color'] ?? '#111111',
            'textAlign' => $style['textAlign'] ?? 'left',
        ];
    }

    private function imageField(string $key, float $x, float $y, float $w, float $h): array
    {
        return [
            'id' => 'fld_seed_'.Str::slug($key),
            'objectType' => 'variable',
            'variableKey' => $key,
            'dataType' => 'image',
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'rotation' => 0,
        ];
    }

    private function assetField(string $path, float $x, float $y, float $w, float $h, string $label): array
    {
        return [
            'id' => 'fld_seed_'.Str::slug($label),
            'objectType' => 'asset',
            'dataType' => 'asset',
            'label' => $label,
            'imagePath' => $path,
            'imageUrl' => '/storage/'.$path,
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'rotation' => 0,
        ];
    }

    /** @param array<string, mixed> $style */
    private function lineField(string $direction, float $x, float $y, float $w, float $h, array $style = []): array
    {
        return [
            'id' => 'fld_seed_line_'.Str::random(4),
            'objectType' => 'line',
            'dataType' => 'line',
            'label' => 'Line',
            'lineDirection' => $direction,
            'lineThickness' => $style['lineThickness'] ?? 0.4,
            'lineStyle' => 'solid',
            'color' => $style['color'] ?? '#111111',
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'rotation' => 0,
        ];
    }
}
