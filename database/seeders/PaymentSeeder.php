<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->first();

        PaymentSetting::updateOrCreate(
            ['tenant_id' => $tenant?->id],
            [
                'upi_id' => 'littlestars@upi',
                'account_name' => 'Little Stars Kindergarten',
                'account_number' => '123456789012',
                'ifsc_code' => 'HDFC0001234',
                'bank_name' => 'HDFC Bank',
                'branch' => 'Pune Camp',
                'enable_upi' => true,
                'enable_cash' => true,
                'enable_qr' => true,
                'enable_razorpay' => false,
                'payment_note' => 'Fee payments accepted via UPI, QR scan, or cash at office.',
            ],
        );

        $rows = [
            ['Aarav Patel', 'ADM-2025-001', 'Rajesh Parent', '+91 90000 00000', 5000, 'upi', 'UPI-REF-8821', 'pending'],
            ['Ananya Sharma', 'ADM-2025-002', 'Priya Sharma', '+91 90000 00000', 3500, 'cash', 'CASH-REC-104', 'verified'],
            ['Vihaan Mehta', 'ADM-2025-003', 'Suresh Mehta', '+91 98765 33333', 4500, 'qr', 'QR-PAY-3390', 'pending'],
            ['Isha Gupta', 'ADM-2025-004', 'Neha Gupta', '+91 98765 44444', 6000, 'upi', 'UPI-REF-9912', 'verified'],
        ];

        foreach ($rows as [$student, $adm, $payer, $phone, $amount, $method, $ref, $status]) {
            Payment::updateOrCreate(
                ['payment_reference' => $ref],
                [
                    'tenant_id' => $tenant?->id,
                    'student_name' => $student,
                    'admission_number' => $adm,
                    'payer_name' => $payer,
                    'payer_phone' => $phone,
                    'amount' => $amount,
                    'payment_method' => $method,
                    'status' => $status,
                    'verified_at' => $status === 'verified' ? now()->subDays(2) : null,
                ],
            );
        }
    }
}
