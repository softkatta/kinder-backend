<?php

namespace Database\Seeders;

use App\Models\IdCard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class IdCardSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->first();
        $year = date('Y').'-'.(date('Y') + 1);
        $parentUser = User::query()->where('email', 'parent@littlestars.com')->first();
        $studentUser = User::query()->where('email', 'student@littlestars.com')->first();
        $teacherUser = User::query()->where('email', 'teacher@littlestars.com')->first();

        $cards = [
            [
                'card_number' => 'STU-DEMO001',
                'qr_token' => 'LS-DEMO-STU-AARAV001',
                'card_type' => 'student',
                'full_name' => 'Aarav Patel',
                'user_id' => $studentUser?->id,
                'blood_group' => 'B+',
                'academic_year' => $year,
                'emergency_contact' => '+91 91234 56789',
                'meta' => [
                    'admission_number' => 'ADM-2025-001',
                    'roll_number' => '12',
                    'class' => 'Nursery',
                    'class_name' => 'Nursery',
                    'section_name' => 'A',
                    'parent_name' => 'Rajesh Parent',
                    'parent_email' => 'parent@littlestars.com',
                    'parent_phone' => '+91 90000 00000',
                ],
            ],
            [
                'card_number' => 'STU-DEMO002',
                'qr_token' => 'LS-DEMO-STU-ANANYA02',
                'card_type' => 'student',
                'full_name' => 'Ananya Sharma',
                'blood_group' => 'O+',
                'academic_year' => $year,
                'emergency_contact' => '+91 99887 76655',
                'meta' => [
                    'admission_number' => 'ADM-2025-002',
                    'roll_number' => '08',
                    'class' => 'LKG',
                    'class_name' => 'LKG',
                    'section_name' => 'B',
                    'parent_name' => 'Priya Sharma',
                    'parent_email' => 'parent@littlestars.com',
                    'parent_phone' => '+91 90000 00000',
                ],
            ],
            [
                'card_number' => 'TCH-DEMO001',
                'qr_token' => 'LS-DEMO-TCH-SNEHA001',
                'card_type' => 'teacher',
                'full_name' => 'Ms. Sneha Desai',
                'user_id' => $teacherUser?->id,
                'academic_year' => $year,
                'emergency_contact' => '+91 90000 11111',
                'meta' => [
                    'employee_id' => 'EMP-T-001',
                    'designation' => 'Nursery Teacher',
                    'assigned_class' => 'Nursery',
                    'department' => 'Early Years',
                ],
            ],
            [
                'card_number' => 'STF-DEMO001',
                'qr_token' => 'LS-DEMO-STF-RAMESH01',
                'card_type' => 'staff',
                'full_name' => 'Ramesh Kulkarni',
                'academic_year' => $year,
                'emergency_contact' => '+91 90000 22222',
                'meta' => [
                    'employee_id' => 'EMP-S-004',
                    'designation' => 'Office Administrator',
                    'department' => 'Administration',
                ],
            ],
            [
                'card_number' => 'PAR-DEMO001',
                'qr_token' => 'LS-DEMO-PAR-RAJESH01',
                'card_type' => 'parent',
                'full_name' => 'Rajesh Parent',
                'user_id' => $parentUser?->id,
                'academic_year' => $year,
                'emergency_contact' => '+91 90000 00000',
                'meta' => [
                    'parent_id' => 'PAR-001',
                    'relationship' => 'Father',
                    'student_names' => 'Aarav Patel',
                ],
            ],
            [
                'card_number' => 'GST-DEMO001',
                'qr_token' => 'LS-DEMO-GST-MEERA001',
                'card_type' => 'guest',
                'full_name' => 'Dr. Meera Iyer',
                'issue_date' => now()->toDateString(),
                'expiry_date' => now()->addDays(3)->toDateString(),
                'academic_year' => $year,
                'meta' => [
                    'visitor_id' => 'GST-2025-014',
                    'company' => 'EduConsult India',
                    'purpose' => 'Curriculum Review',
                    'valid_from' => now()->format('d M Y'),
                    'valid_until' => now()->addDays(3)->format('d M Y'),
                ],
            ],
        ];

        foreach ($cards as $data) {
            IdCard::updateOrCreate(
                ['card_number' => $data['card_number']],
                [
                    ...$data,
                    'tenant_id' => $tenant?->id,
                    'status' => 'active',
                    'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                    'expiry_date' => $data['expiry_date'] ?? now()->addYear()->toDateString(),
                ],
            );
        }
    }
}
