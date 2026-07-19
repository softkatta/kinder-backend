<?php

namespace Database\Seeders;

use App\Models\FeeCategory;
use App\Models\Homework;
use App\Models\IdCard;
use App\Models\StudentFee;
use App\Models\Tenant;
use App\Models\TransportRoute;
use App\Models\User;
use Illuminate\Database\Seeder;

class ErpModuleSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->first();
        $teacher = User::query()->where('email', 'teacher@littlestars.com')->first();
        $year = date('Y').'-'.(date('Y') + 1);

        $categories = [
            ['name' => 'Annual Tuition', 'code' => 'TUITION', 'amount' => 45000, 'frequency' => 'yearly', 'grade_level' => null],
            ['name' => 'Transport Fee', 'code' => 'TRANSPORT', 'amount' => 12000, 'frequency' => 'yearly', 'grade_level' => null],
            ['name' => 'Activity Fee', 'code' => 'ACTIVITY', 'amount' => 5000, 'frequency' => 'yearly', 'grade_level' => null],
            ['name' => 'Nursery Tuition', 'code' => 'NURSERY', 'amount' => 42000, 'frequency' => 'yearly', 'grade_level' => 'Nursery'],
        ];

        $feeCategories = [];
        foreach ($categories as $cat) {
            $feeCategories[$cat['code']] = FeeCategory::updateOrCreate(
                ['code' => $cat['code'], 'tenant_id' => $tenant?->id],
                [...$cat, 'tenant_id' => $tenant?->id, 'is_active' => true],
            );
        }

        $routeA = TransportRoute::updateOrCreate(
            ['name' => 'Route A — Koregaon Park', 'tenant_id' => $tenant?->id],
            [
                'area' => 'Koregaon Park',
                'pickup_points' => 'Lane 5, Main Road, ABC Society',
                'driver_name' => 'Ravi Patil',
                'driver_phone' => '+91 98765 11111',
                'vehicle_number' => 'MH-12-AB-4521',
                'monthly_fee' => 1200,
                'status' => 'active',
            ],
        );

        $routeB = TransportRoute::updateOrCreate(
            ['name' => 'Route B — Kalyani Nagar', 'tenant_id' => $tenant?->id],
            [
                'area' => 'Kalyani Nagar',
                'pickup_points' => 'Central Mall, Park View, River Side',
                'driver_name' => 'Sunil Jadhav',
                'driver_phone' => '+91 98765 22222',
                'vehicle_number' => 'MH-12-CD-8899',
                'monthly_fee' => 1000,
                'status' => 'active',
            ],
        );

        $aarav = IdCard::query()->where('card_number', 'STU-DEMO001')->first();
        $ananya = IdCard::query()->where('card_number', 'STU-DEMO002')->first();

        if ($aarav) {
            $aarav->update(['transport_route_id' => $routeA->id]);

            StudentFee::updateOrCreate(
                ['id_card_id' => $aarav->id, 'fee_category_id' => $feeCategories['TUITION']->id, 'academic_year' => $year],
                [
                    'tenant_id' => $tenant?->id,
                    'title' => 'Annual Tuition',
                    'amount' => 45000,
                    'paid_amount' => 15000,
                    'due_date' => now()->addMonths(2),
                    'status' => 'partial',
                ],
            );

            StudentFee::updateOrCreate(
                ['id_card_id' => $aarav->id, 'fee_category_id' => $feeCategories['TRANSPORT']->id, 'academic_year' => $year],
                [
                    'tenant_id' => $tenant?->id,
                    'title' => 'Transport Fee',
                    'amount' => 12000,
                    'paid_amount' => 0,
                    'due_date' => now()->addMonth(),
                    'status' => 'pending',
                ],
            );
        }

        if ($ananya) {
            $ananya->update(['transport_route_id' => $routeB->id]);

            StudentFee::updateOrCreate(
                ['id_card_id' => $ananya->id, 'fee_category_id' => $feeCategories['NURSERY']->id, 'academic_year' => $year],
                [
                    'tenant_id' => $tenant?->id,
                    'title' => 'Nursery Tuition',
                    'amount' => 42000,
                    'paid_amount' => 42000,
                    'due_date' => now()->subMonth(),
                    'status' => 'paid',
                ],
            );
        }

        Homework::updateOrCreate(
            ['title' => 'Colour the Rainbow', 'class_name' => 'Nursery', 'tenant_id' => $tenant?->id],
            [
                'teacher_user_id' => $teacher?->id,
                'body' => 'Use crayons to colour the rainbow worksheet. Bring it to school on Monday.',
                'due_date' => now()->addDays(4),
                'emoji' => '🌈',
                'status' => 'active',
            ],
        );

        Homework::updateOrCreate(
            ['title' => 'Count Objects 1–10', 'class_name' => 'Nursery', 'tenant_id' => $tenant?->id],
            [
                'teacher_user_id' => $teacher?->id,
                'body' => 'Count toys at home and draw how many you found.',
                'due_date' => now()->addDays(7),
                'emoji' => '🔢',
                'status' => 'active',
            ],
        );

        Homework::updateOrCreate(
            ['title' => 'Letter A Practice', 'class_name' => 'LKG', 'tenant_id' => $tenant?->id],
            [
                'teacher_user_id' => $teacher?->id,
                'body' => 'Practice writing letter A in the notebook.',
                'due_date' => now()->addDays(5),
                'emoji' => '✏️',
                'status' => 'active',
            ],
        );
    }
}
