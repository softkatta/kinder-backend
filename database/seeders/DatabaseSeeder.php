<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'little-stars'],
            [
                'name' => 'Little Stars Kindergarten',
                'email' => 'info@littlestars.com',
                'phone' => '+91 98765 43210',
                'is_active' => true,
            ],
        );

        $roles = collect([
            ['name' => 'super_admin', 'label' => 'Super Admin'],
            ['name' => 'teacher', 'label' => 'Teacher'],
            ['name' => 'staff', 'label' => 'Staff'],
            ['name' => 'parent', 'label' => 'Parent'],
            ['name' => 'student', 'label' => 'Student'],
            ['name' => 'guest', 'label' => 'Guest'],
        ])->mapWithKeys(fn (array $role) => [
            $role['name'] => Role::query()->firstOrCreate(
                ['name' => $role['name']],
                ['label' => $role['label']],
            ),
        ]);

        $users = [
            // Super admin is created only by the SoftKatta install wizard — do not seed here.
            ['name' => 'Priya Teacher', 'email' => 'teacher@littlestars.com', 'role' => 'teacher'],
            ['name' => 'Suresh Staff', 'email' => 'staff@littlestars.com', 'role' => 'staff'],
            ['name' => 'Rajesh Parent', 'email' => 'parent@littlestars.com', 'role' => 'parent'],
            ['name' => 'Aarav Student', 'email' => 'student@littlestars.com', 'role' => 'student'],
        ];

        foreach ($users as $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'phone' => '+91 90000 00000',
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );

            $user->roles()->syncWithoutDetaching([$roles[$data['role']]->id]);
        }

        $this->call(CmsSeeder::class);
        $this->call(IdCardSeeder::class);
        $this->call(SampleMediaSeeder::class);
        $this->call(AdmissionSeeder::class);
        $this->call(AttendanceSeeder::class);
        $this->call(AcademicExamGuestSeeder::class);
        $this->call(PaymentSeeder::class);
        $this->call(GuestPortalSeeder::class);
        $this->call(ScanCodeSeeder::class);
        $this->call(LiveStreamSeeder::class);
        $this->call(ErpModuleSeeder::class);
        $this->call(TemplateDesignerSeeder::class);
    }
}
