<?php

namespace App\Services\IdCard;

use App\Models\AttendanceRecord;
use App\Models\IdCard;
use App\Models\IdCardScanLog;
use App\Models\Tenant;
use App\Services\IdCard\IdCardService;
use App\Services\QrScanResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QrVerificationService
{
    private const ATTENDANCE_TYPES = ['student', 'teacher', 'staff'];

    public function __construct(
        private readonly IdCardService $idCardService,
        private readonly QrScanResolver $scanResolver,
    ) {}

    public function verifyOnly(string $qrToken, int $scannedByUserId, array $context = []): array
    {
        $card = $this->resolveCard($qrToken, $scannedByUserId, $context);
        $view = $this->idCardService->toCardViewData($card);

        $todayRecord = AttendanceRecord::query()
            ->where('id_card_id', $card->id)
            ->whereDate('date', Carbon::today())
            ->first();

        $this->log($card, $qrToken, 'success', $scannedByUserId, [
            ...$context,
            'verify_only' => true,
            'attendance' => false,
        ]);

        $base = [
            'action' => 'verified',
            'message' => 'Card verified successfully.',
            'card' => $view,
            'card_type' => $card->card_type,
            'attendance' => $todayRecord ? [
                'date' => $todayRecord->date->toDateString(),
                'status' => $todayRecord->status,
                'check_in_time' => $todayRecord->check_in_time,
                'check_out_time' => $todayRecord->check_out_time,
            ] : null,
        ];

        if ($card->card_type === 'student') {
            return [...$base, ...$this->studentPayload($card)];
        }

        $meta = $card->meta ?? [];

        return [
            ...$base,
            'person' => [
                'id' => $card->id,
                'full_name' => $card->full_name,
                'card_type' => $card->card_type,
                'employee_id' => $meta['employee_id'] ?? $card->card_number,
                'department' => $meta['department'] ?? null,
                'designation' => $meta['designation'] ?? null,
            ],
        ];
    }

    public function verifyAndScan(string $qrToken, int $scannedByUserId, array $context = []): array
    {
        $card = $this->resolveCard($qrToken, $scannedByUserId, $context);
        $view = $this->idCardService->toCardViewData($card);
        $today = Carbon::today();

        if (! in_array($card->card_type, self::ATTENDANCE_TYPES, true)) {
            $this->log($card, $qrToken, 'success', $scannedByUserId, [
                ...$context,
                'attendance' => false,
                'message' => 'Card verified successfully.',
            ]);

            return [
                'action' => 'verified',
                'message' => 'Card verified successfully.',
                'card' => $view,
                'attendance' => null,
            ];
        }

        return DB::transaction(function () use ($card, $qrToken, $scannedByUserId, $context, $view, $today) {
            $record = AttendanceRecord::query()
                ->where('id_card_id', $card->id)
                ->whereDate('date', $today)
                ->lockForUpdate()
                ->first();

            $now = now()->format('H:i:s');
            $tenant = Tenant::query()->first();

            if (! $record) {
                $record = AttendanceRecord::create([
                    'tenant_id' => $tenant?->id,
                    'id_card_id' => $card->id,
                    'date' => $today,
                    'status' => 'present',
                    'check_in_time' => $now,
                    'method' => 'qr',
                    'marked_by' => $scannedByUserId,
                ]);

                $this->log($card, $qrToken, 'success', $scannedByUserId, [
                    ...$context,
                    'action' => 'check_in',
                ]);

                return $this->buildAttendanceResponse($card, $view, $record, 'check_in', 'Check-in recorded successfully.');
            }

            if ($record->check_out_time) {
                $this->log($card, $qrToken, 'duplicate', $scannedByUserId, [
                    ...$context,
                    'action' => 'already_complete',
                ]);

                return $this->buildAttendanceResponse($card, $view, $record, 'already_complete', 'Attendance already completed for today.');
            }

            $record->update([
                'check_out_time' => $now,
                'marked_by' => $scannedByUserId,
            ]);

            $this->log($card, $qrToken, 'success', $scannedByUserId, [
                ...$context,
                'action' => 'check_out',
            ]);

            return $this->buildAttendanceResponse($card, $view, $record->fresh(), 'check_out', 'Check-out recorded successfully.');
        });
    }

    private function resolveCard(string $qrToken, int $scannedByUserId, array $context): IdCard
    {
        $card = $this->scanResolver->findIdCard($qrToken);

        if (! $card) {
            $this->log(null, $qrToken, 'invalid', $scannedByUserId, $context);
            throw ValidationException::withMessages(['code_value' => 'Invalid QR code. Card not recognized.']);
        }

        if ($card->status === 'blocked') {
            $this->log($card, $qrToken, 'blocked', $scannedByUserId, $context);
            throw ValidationException::withMessages(['code_value' => 'This card has been blocked. Contact the school office.']);
        }

        if ($card->status === 'inactive') {
            $this->log($card, $qrToken, 'inactive', $scannedByUserId, $context);
            throw ValidationException::withMessages(['code_value' => 'This card is inactive.']);
        }

        if ($card->isExpired() || $card->status === 'expired') {
            $this->log($card, $qrToken, 'expired', $scannedByUserId, $context);
            throw ValidationException::withMessages(['code_value' => 'This card has expired.']);
        }

        return $card;
    }

    private function buildAttendanceResponse(IdCard $card, array $view, AttendanceRecord $record, string $action, string $message): array
    {
        $base = [
            'action' => $action,
            'message' => $message,
            'card' => $view,
            'card_type' => $card->card_type,
            'attendance' => [
                'date' => $record->date->toDateString(),
                'status' => $record->status,
                'check_in_time' => $record->check_in_time,
                'check_out_time' => $record->check_out_time,
            ],
        ];

        if ($card->card_type === 'student') {
            return [...$base, ...$this->studentPayload($card)];
        }

        $meta = $card->meta ?? [];

        return [
            ...$base,
            'person' => [
                'id' => $card->id,
                'full_name' => $card->full_name,
                'card_type' => $card->card_type,
                'employee_id' => $meta['employee_id'] ?? $card->card_number,
                'department' => $meta['department'] ?? null,
                'designation' => $meta['designation'] ?? null,
            ],
        ];
    }

    private function studentPayload(IdCard $card): array
    {
        $meta = $card->meta ?? [];

        return [
            'student' => [
                'id' => $card->id,
                'full_name' => $card->full_name,
                'admission_number' => $meta['admission_number'] ?? $card->card_number,
                'roll_number' => $meta['roll_number'] ?? null,
                'photo_path' => $card->photo_path,
                'blood_group' => $card->blood_group,
                'status' => $card->status,
                'class' => ['name' => $meta['class_name'] ?? '—'],
                'section' => ['name' => $meta['section_name'] ?? '—'],
                'parent' => isset($meta['parent_name']) ? [
                    'full_name' => $meta['parent_name'],
                    'phone' => $meta['parent_phone'] ?? null,
                ] : null,
                'qr_code' => $card->qr_token,
            ],
        ];
    }

    private function log(?IdCard $card, string $qrToken, string $result, int $userId, array $payload): void
    {
        $tenant = Tenant::query()->first();

        IdCardScanLog::create([
            'tenant_id' => $tenant?->id,
            'id_card_id' => $card?->id,
            'qr_token' => $qrToken,
            'result' => $result,
            'scanned_by' => $userId,
            'payload' => $payload,
            'ip_address' => request()->ip(),
        ]);
    }

    public function scanHistory(?int $cardId = null, int $limit = 50): array
    {
        $query = IdCardScanLog::query()
            ->with(['idCard:id,full_name,card_type,card_number', 'scannedByUser:id,name'])
            ->latest();

        if ($cardId) {
            $query->where('id_card_id', $cardId);
        }

        return $query->limit($limit)->get()->toArray();
    }
}
