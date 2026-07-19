<?php

namespace Database\Seeders;

use App\Models\Admission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdmissionSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->first();
        $reviewer = User::query()->where('email', 'teacher@littlestars.com')->first();

        $rows = [
            [
                'applicant_name' => 'Riya Joshi',
                'dob' => '2023-04-12',
                'gender' => 'female',
                'grade_level' => 'nursery',
                'status' => 'pending',
                'photo_path' => 'sample/avatars/admission-1.jpg',
                'parent_info' => [
                    'father_name' => 'Amit Joshi',
                    'mother_name' => 'Pooja Joshi',
                    'email' => 'joshi.family@example.com',
                    'phone' => '+91 98111 22001',
                ],
                'address_info' => [
                    'line1' => '12 Green Park',
                    'city' => 'Pune',
                    'pincode' => '411001',
                ],
                'remarks' => null,
            ],
            [
                'applicant_name' => 'Kabir Singh',
                'dob' => '2022-08-03',
                'gender' => 'male',
                'grade_level' => 'lkg',
                'status' => 'review',
                'photo_path' => 'sample/avatars/admission-2.jpg',
                'parent_info' => [
                    'father_name' => 'Vikram Singh',
                    'mother_name' => 'Anjali Singh',
                    'email' => 'singh.family@example.com',
                    'phone' => '+91 98111 22002',
                ],
                'address_info' => [
                    'line1' => '45 Lake View Road',
                    'city' => 'Pune',
                    'pincode' => '411004',
                ],
                'remarks' => 'Documents pending verification',
            ],
            [
                'applicant_name' => 'Sara Khan',
                'dob' => '2021-11-20',
                'gender' => 'female',
                'grade_level' => 'ukg',
                'status' => 'approved',
                'photo_path' => 'sample/avatars/admission-3.jpg',
                'parent_info' => [
                    'father_name' => 'Imran Khan',
                    'mother_name' => 'Fatima Khan',
                    'email' => 'khan.family@example.com',
                    'phone' => '+91 98111 22003',
                ],
                'address_info' => [
                    'line1' => '8 Rose Avenue',
                    'city' => 'Pune',
                    'pincode' => '411007',
                ],
                'remarks' => 'Seat confirmed for UKG A',
                'reviewed_by_user_id' => $reviewer?->id,
                'reviewed_at' => now()->subDays(2),
            ],
            [
                'applicant_name' => 'Advait Nair',
                'dob' => '2023-01-15',
                'gender' => 'male',
                'grade_level' => 'nursery',
                'status' => 'rejected',
                'photo_path' => null,
                'parent_info' => [
                    'father_name' => 'Suresh Nair',
                    'mother_name' => 'Meera Nair',
                    'email' => 'nair.family@example.com',
                    'phone' => '+91 98111 22004',
                ],
                'address_info' => [
                    'line1' => 'Out of transport zone',
                    'city' => 'Pune',
                    'pincode' => '411045',
                ],
                'remarks' => 'Transport route unavailable',
                'reviewed_by_user_id' => $reviewer?->id,
                'reviewed_at' => now()->subDays(5),
            ],
            [
                'applicant_name' => 'Myra Deshmukh',
                'dob' => '2022-06-28',
                'gender' => 'female',
                'grade_level' => 'lkg',
                'status' => 'pending',
                'photo_path' => null,
                'parent_info' => [
                    'father_name' => 'Nikhil Deshmukh',
                    'mother_name' => 'Shruti Deshmukh',
                    'email' => 'deshmukh.family@example.com',
                    'phone' => '+91 98111 22005',
                ],
                'address_info' => [
                    'line1' => '22 Baner Road',
                    'city' => 'Pune',
                    'pincode' => '411045',
                ],
                'remarks' => null,
            ],
        ];

        foreach ($rows as $row) {
            Admission::updateOrCreate(
                [
                    'tenant_id' => $tenant?->id,
                    'applicant_name' => $row['applicant_name'],
                    'dob' => $row['dob'],
                ],
                [
                    ...$row,
                    'tenant_id' => $tenant?->id,
                ],
            );
        }
    }
}
