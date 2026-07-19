<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\IdCard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->first();
        $marker = User::query()->where('email', 'teacher@littlestars.com')->first();

        $students = IdCard::query()
            ->where('card_type', 'student')
            ->whereIn('card_number', ['STU-DEMO001', 'STU-DEMO002', 'STU-DEMO003'])
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $statuses = ['present', 'present', 'present', 'late', 'absent'];

        for ($day = 14; $day >= 0; $day--) {
            $date = now()->subDays($day);
            if ($date->isSunday()) {
                continue;
            }

            foreach ($students as $i => $student) {
                $status = $statuses[($day + $i) % count($statuses)];
                AttendanceRecord::updateOrCreate(
                    [
                        'tenant_id' => $tenant?->id,
                        'id_card_id' => $student->id,
                        'date' => $date->toDateString(),
                    ],
                    [
                        'status' => $status,
                        'check_in_time' => $status === 'absent' ? null : $date->copy()->setTime(9, 10 + $i)->format('H:i:s'),
                        'check_out_time' => $status === 'absent' ? null : $date->copy()->setTime(13, 30)->format('H:i:s'),
                        'method' => 'manual',
                        'marked_by' => $marker?->id,
                        'remarks' => $status === 'late' ? 'Arrived after circle time' : null,
                    ],
                );
            }
        }
    }
}
