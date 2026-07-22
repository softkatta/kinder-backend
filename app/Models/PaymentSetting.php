<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSetting extends Model
{
    protected $fillable = [
        'tenant_id', 'upi_id', 'account_name', 'account_number', 'ifsc_code', 'bank_name', 'branch',
        'upi_qr_path', 'enable_upi', 'enable_cash', 'enable_qr', 'enable_razorpay',
        'razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret', 'payment_note',
    ];

    protected function casts(): array
    {
        return [
            'enable_upi' => 'boolean',
            'enable_cash' => 'boolean',
            'enable_qr' => 'boolean',
            'enable_razorpay' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
