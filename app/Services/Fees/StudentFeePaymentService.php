<?php

namespace App\Services\Fees;

use App\Models\IdCard;
use App\Models\Payment;
use App\Models\StudentFee;
use Illuminate\Support\Collection;

class StudentFeePaymentService
{
    /** Apply verified payment amount to outstanding student fees (oldest due first). */
    public function applyPayment(Payment $payment): void
    {
        if ($payment->status !== 'verified') {
            return;
        }

        $card = $this->resolveStudentCard($payment);
        if (! $card) {
            return;
        }

        $remaining = (float) $payment->amount;
        if ($remaining <= 0) {
            return;
        }

        $fees = StudentFee::query()
            ->where('id_card_id', $card->id)
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        foreach ($fees as $fee) {
            if ($remaining <= 0) {
                break;
            }

            $balance = $fee->balance();
            if ($balance <= 0) {
                continue;
            }

            $apply = min($balance, $remaining);
            $fee->update([
                'paid_amount' => (float) $fee->paid_amount + $apply,
                'status' => $apply >= $balance ? 'paid' : 'partial',
            ]);
            $remaining -= $apply;
        }
    }

    /** Reverse a previously applied verified payment amount from student fees (newest first). */
    public function reversePayment(Payment $payment): void
    {
        $card = $this->resolveStudentCard($payment);
        if (! $card) {
            return;
        }

        $remaining = (float) $payment->amount;
        if ($remaining <= 0) {
            return;
        }

        $fees = StudentFee::query()
            ->where('id_card_id', $card->id)
            ->where('paid_amount', '>', 0)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get();

        foreach ($fees as $fee) {
            if ($remaining <= 0) {
                break;
            }

            $paid = (float) $fee->paid_amount;
            if ($paid <= 0) {
                continue;
            }

            $reverse = min($paid, $remaining);
            $newPaid = $paid - $reverse;
            $fee->update([
                'paid_amount' => $newPaid,
                'status' => $newPaid <= 0 ? 'pending' : ($newPaid < (float) $fee->amount ? 'partial' : 'paid'),
            ]);
            $remaining -= $reverse;
        }
    }

    public function resolveStudentCard(Payment $payment): ?IdCard
    {
        if ($payment->admission_number) {
            $byNumber = IdCard::query()
                ->where('card_type', 'student')
                ->where(function ($q) use ($payment) {
                    $q->where('card_number', $payment->admission_number)
                        ->orWhereJsonContains('meta->admission_number', $payment->admission_number);
                })
                ->first();
            if ($byNumber) {
                return $byNumber;
            }
        }

        if ($payment->student_name) {
            return IdCard::query()
                ->where('card_type', 'student')
                ->where('full_name', $payment->student_name)
                ->first();
        }

        return null;
    }

    /** @return array{fee_total: float, fee_paid: float, fee_balance: float, fees: Collection<int, StudentFee>} */
    public function studentFeeSummary(IdCard $student): array
    {
        $fees = StudentFee::query()
            ->where('id_card_id', $student->id)
            ->orderBy('due_date')
            ->get();

        $total = (float) $fees->sum('amount');
        $paid = (float) $fees->sum('paid_amount');

        return [
            'fee_total' => $total,
            'fee_paid' => $paid,
            'fee_balance' => max(0, $total - $paid),
            'fees' => $fees,
        ];
    }
}
