<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Admission;
use App\Models\AttendanceRecord;
use App\Models\IdCard;
use App\Models\Payment;
use App\Models\StudentFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function attendance(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $records = AttendanceRecord::query()
            ->whereBetween('date', [$from, $to])
            ->with('idCard:id,full_name,card_type,card_number')
            ->get();

        $byStatus = $records->groupBy('status')->map->count();
        $byType = $records->groupBy(fn ($r) => $r->idCard?->card_type ?? 'unknown')->map->count();

        return ApiResponse::success([
            'from' => $from,
            'to' => $to,
            'total_records' => $records->count(),
            'by_status' => $byStatus,
            'by_card_type' => $byType,
            'recent' => $records->sortByDesc('date')->take(20)->values()->map(fn ($r) => [
                'date' => $r->date->toDateString(),
                'status' => $r->status,
                'name' => $r->idCard?->full_name,
                'card_type' => $r->idCard?->card_type,
                'card_number' => $r->idCard?->card_number,
            ]),
        ]);
    }

    public function students(): JsonResponse
    {
        $cards = IdCard::query()->where('card_type', 'student')->get();
        $byClass = $cards->groupBy(fn ($c) => is_array($c->meta) ? ($c->meta['class'] ?? 'Unknown') : 'Unknown')->map->count();
        $byStatus = $cards->groupBy('status')->map->count();

        return ApiResponse::success([
            'total' => $cards->count(),
            'active' => $cards->where('status', 'active')->count(),
            'by_class' => $byClass,
            'by_status' => $byStatus,
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $payments = Payment::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        $verified = $payments->where('status', 'verified');
        $pending = $payments->where('status', 'pending');

        return ApiResponse::success([
            'from' => $from,
            'to' => $to,
            'total_count' => $payments->count(),
            'verified_amount' => (float) $verified->sum('amount'),
            'pending_amount' => (float) $pending->sum('amount'),
            'refunded_count' => $payments->where('status', 'refunded')->count(),
            'by_method' => $payments->groupBy('payment_method')->map(fn ($group) => [
                'count' => $group->count(),
                'amount' => (float) $group->sum('amount'),
            ]),
        ]);
    }

    public function admissions(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $rows = Admission::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        return ApiResponse::success([
            'from' => $from,
            'to' => $to,
            'total' => $rows->count(),
            'by_status' => $rows->groupBy('status')->map->count(),
            'by_grade' => $rows->groupBy(fn ($r) => strtoupper((string) ($r->grade_level ?: 'Unknown')))->map->count(),
            'recent' => $rows->sortByDesc('created_at')->take(10)->values()->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->applicant_name,
                'grade' => $r->grade_level,
                'status' => $r->status,
                'date' => $r->created_at?->format('d M Y'),
            ]),
        ]);
    }

    public function fees(): JsonResponse
    {
        $fees = StudentFee::query()->with('idCard:id,full_name')->get();
        $pendingPayments = Payment::query()->where('status', 'pending')->get();

        return ApiResponse::success([
            'assigned_count' => $fees->count(),
            'total_assigned' => (float) $fees->sum('amount'),
            'total_collected' => (float) $fees->sum('paid_amount'),
            'total_outstanding' => (float) $fees->sum(fn (StudentFee $f) => $f->balance()),
            'pending_verification_count' => $pendingPayments->count(),
            'pending_verification_amount' => (float) $pendingPayments->sum('amount'),
            'by_status' => $fees->groupBy('status')->map->count(),
        ]);
    }
}
