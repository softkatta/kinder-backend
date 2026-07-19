<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = ['upi', 'cash', 'qr', 'razorpay'];

    public const STATUSES = ['pending', 'verified', 'rejected', 'refunded'];

    protected $fillable = [
        'tenant_id', 'student_name', 'admission_number', 'payer_name', 'payer_phone',
        'amount', 'payment_method', 'payment_reference', 'status', 'proof_path', 'remarks',
        'verified_at', 'verified_by_user_id', 'refunded_at', 'refunded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
