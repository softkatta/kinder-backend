<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AttendanceRecord;
use App\Models\IdCard;
use App\Services\IdCard\QrVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly QrVerificationService $qrService,
    ) {}

    public function qrMark(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code_value' => 'required|string|max:64',
        ]);

        try {
            $result = $this->qrService->verifyAndScan($data['code_value'], $request->user()->id);

            return ApiResponse::success($result, $result['message'] ?? 'OK');
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first() ?? 'Invalid QR code', 422);
        }
    }

    public function resolveCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:128',
        ]);

        $resolver = app(\App\Services\QrScanResolver::class);
        $key = $resolver->normalizeInput($data['code']);

        if ($resolver->findGuest($key)) {
            return ApiResponse::success(['type' => 'guest']);
        }

        if ($resolver->findIdCard($key)) {
            return ApiResponse::success(['type' => 'id_card']);
        }

        return ApiResponse::error('QR code not recognized', 404);
    }

    public function daily(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $type = $request->query('type');

        $query = AttendanceRecord::query()
            ->with(['idCard', 'markedByUser:id,name'])
            ->whereDate('date', $date)
            ->orderByDesc('check_in_time');

        if ($type) {
            $query->whereHas('idCard', fn ($q) => $q->where('card_type', $type));
        }

        $records = $query->get()->map(fn (AttendanceRecord $r) => [
            'id' => $r->id,
            'date' => $r->date->toDateString(),
            'status' => $r->status,
            'check_in_time' => $r->check_in_time,
            'check_out_time' => $r->check_out_time,
            'method' => $r->method,
            'marked_by_name' => $r->markedByUser?->name,
            'person_name' => $r->idCard?->full_name,
            'card_type' => $r->idCard?->card_type,
            'card_number' => $r->idCard?->card_number,
            'meta' => $r->idCard?->meta ?? [],
        ]);

        return ApiResponse::success($records);
    }

    public function monthly(Request $request, IdCard $student): JsonResponse
    {
        if ($student->card_type !== 'student') {
            return ApiResponse::error('Student not found', 404);
        }

        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $start = now()->setDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $records = AttendanceRecord::query()
            ->where('id_card_id', $student->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get();

        $present = $records->where('status', 'present')->count();
        $absent = $records->where('status', 'absent')->count();

        return ApiResponse::success([
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'month' => $month,
            'year' => $year,
            'present' => $present,
            'absent' => $absent,
            'total_marked' => $records->count(),
            'days' => $records->map(fn (AttendanceRecord $r) => [
                'date' => $r->date->toDateString(),
                'status' => $r->status,
                'check_in_time' => $r->check_in_time,
            ])->values(),
        ]);
    }
}
