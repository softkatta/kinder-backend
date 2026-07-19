<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFee extends Model
{
    public const STATUSES = ['pending', 'partial', 'paid', 'waived'];

    protected $fillable = [
        'tenant_id', 'id_card_id', 'fee_category_id', 'title', 'amount', 'paid_amount',
        'due_date', 'status', 'academic_year', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function idCard(): BelongsTo
    {
        return $this->belongsTo(IdCard::class);
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class);
    }

    public function balance(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }
}
