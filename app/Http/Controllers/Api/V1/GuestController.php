<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Guest;
use App\Models\Tenant;
use App\Services\Guest\GuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SoftKatta\Licensing\Services\LicenseService;

class GuestController extends Controller
{
    public function __construct(
        private readonly GuestService $guests,
        private readonly LicenseService $license,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Guest::query()->withCount('companions')->latest('event_date');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('guest_code', 'like', "%{$search}%")
                    ->orWhere('event_name', 'like', "%{$search}%");
            });
        }

        return ApiResponse::success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $this->license->assertWithinLimit('max_guests', Guest::query()->count());
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        $data = $this->validatedGuest($request);
        $tenant = Tenant::query()->first();

        $guest = Guest::create([
            ...$data,
            'tenant_id' => $tenant?->id,
            'guest_code' => $this->guests->generateGuestCode(),
            'qr_token' => $this->guests->generateQrToken(),
            'scan_code' => app(\App\Services\QrScanResolver::class)->generateScanCode(),
        ]);

        $this->syncCompanions($guest, $request->input('companions', []));
        $this->guests->syncPortalUser($guest->fresh(), $request->input('portal_password'));

        return ApiResponse::success($this->guests->toViewData($guest->fresh()), 'Guest created', 201);
    }

    public function show(Guest $guest): JsonResponse
    {
        return ApiResponse::success($this->guests->toViewData($guest));
    }

    public function update(Request $request, Guest $guest): JsonResponse
    {
        $guest->update($this->validatedGuest($request, $guest->id));

        if ($request->has('companions')) {
            $this->syncCompanions($guest, $request->input('companions', []));
        }

        if ($request->has('portal_password') && $request->input('portal_password')) {
            $this->guests->syncPortalUser($guest->fresh(), $request->input('portal_password'));
        } else {
            $this->guests->syncPortalUser($guest->fresh());
        }

        return ApiResponse::success($this->guests->toViewData($guest->fresh()), 'Updated');
    }

    public function destroy(Guest $guest): JsonResponse
    {
        $guest->delete();

        return ApiResponse::success(null, 'Deleted');
    }

    public function verify(Request $request): JsonResponse
    {
        $token = trim($request->validate(['qr_token' => ['required', 'string']])['qr_token']);

        $guest = $this->guests->resolveGuest($token);

        if (! $guest) {
            return ApiResponse::error('Guest pass not found', 404);
        }

        if (! $guest->isScannable()) {
            return ApiResponse::error('Guest pass is inactive or expired', 422);
        }

        return ApiResponse::success([
            'action' => 'verified',
            'guest' => $this->guests->toViewData($guest),
        ]);
    }

    public function entry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr_token' => ['required', 'string'],
            'direction' => ['required', Rule::in(['in', 'out', 'toggle'])],
            'guest_companion_id' => ['nullable', 'integer', 'exists:guest_companions,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $token = trim($data['qr_token']);
        $guest = $this->guests->resolveGuest($token);

        if (! $guest) {
            return ApiResponse::error('Guest pass not found', 404);
        }

        $companionId = $data['guest_companion_id'] ?? null;
        $direction = $data['direction'] === 'toggle'
            ? $this->guests->suggestedDirection($guest, $companionId)
            : $data['direction'];

        if ($direction === 'in' && ! $guest->isScannable()) {
            return ApiResponse::error('Guest pass is inactive or expired', 422);
        }

        if ($direction === 'out' && ! $guest->isScannable()) {
            $last = $this->guests->lastEntryForPerson($guest, $companionId);
            if (! $last || $last->direction !== 'in') {
                return ApiResponse::error('Guest pass is inactive or expired', 422);
            }
        }

        try {
            $log = $this->guests->recordEntry(
                $guest,
                $direction,
                $companionId,
                $request->user()?->id,
                $data['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            'action' => $direction === 'in' ? 'check_in' : 'check_out',
            'direction' => $direction,
            'log' => $log,
            'guest' => $this->guests->toViewData($guest->fresh()),
        ], ($direction === 'in' ? 'Entry IN' : 'Entry OUT').' recorded');
    }

    public function entryLogs(Request $request): JsonResponse
    {
        $query = \App\Models\GuestEntryLog::query()
            ->with(['guest:id,full_name,guest_code,event_name', 'companion:id,full_name'])
            ->latest('scanned_at')
            ->limit(100);

        if ($guestId = $request->query('guest_id')) {
            $query->where('guest_id', $guestId);
        }

        return ApiResponse::success($query->get());
    }

    public function portalProfile(Request $request): JsonResponse
    {
        $guest = $this->guests->guestForUser($request->user());
        if (! $guest) {
            return ApiResponse::error('No guest pass linked to this account', 404);
        }

        return ApiResponse::success($this->guests->toViewData($guest));
    }

    public function updatePortalCompanions(Request $request): JsonResponse
    {
        $guest = $this->guests->guestForUser($request->user());
        if (! $guest) {
            return ApiResponse::error('No guest pass linked to this account', 404);
        }

        if (! $guest->isScannable() && $guest->status !== 'active') {
            return ApiResponse::error('Guest pass is not active', 422);
        }

        $companions = $request->validate([
            'companions' => ['required', 'array', 'max:10'],
            'companions.*.full_name' => ['required', 'string', 'max:120'],
            'companions.*.phone' => ['nullable', 'string', 'max:30'],
            'companions.*.photo_path' => ['nullable', 'string'],
            'companions.*.relation' => ['nullable', 'string', 'max:60'],
            'companions.*.can_entry' => ['sometimes', 'boolean'],
        ])['companions'];

        $this->guests->syncCompanions($guest, $companions);

        return ApiResponse::success($this->guests->toViewData($guest->fresh()), 'Companions updated');
    }

    private function syncCompanions(Guest $guest, array $companions): void
    {
        $this->guests->syncCompanions($guest, $companions);
    }

    private function validatedGuest(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'photo_path' => ['nullable', 'string'],
            'event_name' => ['required', 'string', 'max:160'],
            'event_date' => ['nullable', 'date'],
            'event_location' => ['nullable', 'string', 'max:200'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from'],
            'status' => ['sometimes', Rule::in(Guest::STATUSES)],
            'notes' => ['nullable', 'string'],
            'portal_password' => ['nullable', 'string', 'min:6', 'max:64'],
        ]);
    }
}
