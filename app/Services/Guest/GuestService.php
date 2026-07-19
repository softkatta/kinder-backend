<?php

namespace App\Services\Guest;

use App\Models\Guest;
use App\Models\GuestEntryLog;
use App\Models\Role;
use App\Models\User;
use App\Services\IdCard\IdCardService;
use App\Services\IdCard\QrCodeGenerator;
use App\Services\QrScanResolver;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestService
{
    public function __construct(
        private readonly QrCodeGenerator $qr,
        private readonly IdCardService $idCardService,
        private readonly QrScanResolver $scanResolver,
    ) {}

    public function generateGuestCode(): string
    {
        return 'GST-'.strtoupper(Str::random(8));
    }

    public function generateQrToken(): string
    {
        return 'LS-GUEST-'.strtoupper(Str::random(12));
    }

    public function ensureScanCode(Guest $guest): Guest
    {
        if ($guest->scan_code) {
            return $guest;
        }

        $guest->update(['scan_code' => $this->scanResolver->generateScanCode()]);

        return $guest->fresh();
    }

    public function photoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return url('/storage/'.ltrim($path, '/'));
    }

    public function toViewData(Guest $guest): array
    {
        $guest->load(['companions', 'entryLogs' => fn ($q) => $q->limit(20)]);
        $guest = $this->ensureScanCode($guest);
        $qrPayload = $this->scanResolver->qrPayload($guest->scan_code);

        return [
            'id' => $guest->id,
            'guest_code' => $guest->guest_code,
            'qr_token' => $guest->qr_token,
            'full_name' => $guest->full_name,
            'phone' => $guest->phone,
            'email' => $guest->email,
            'photo_path' => $guest->photo_path,
            'photo_url' => $this->photoUrl($guest->photo_path),
            'event_name' => $guest->event_name,
            'event_date' => $guest->event_date?->format('d M Y'),
            'event_date_raw' => $guest->event_date?->toDateString(),
            'event_location' => $guest->event_location,
            'valid_from' => $guest->valid_from->format('d M Y'),
            'valid_from_raw' => $guest->valid_from->toDateString(),
            'valid_until' => $guest->valid_until->format('d M Y'),
            'valid_until_raw' => $guest->valid_until->toDateString(),
            'status' => $guest->status,
            'notes' => $guest->notes,
            'is_scannable' => $guest->isScannable(),
            'school' => $this->idCardService->schoolProfile(),
            'qr_data_uri' => $this->qr->dataUri($qrPayload),
            'initials' => $this->initials($guest->full_name),
            'companions' => $guest->companions->map(fn ($c) => [
                'id' => $c->id,
                'full_name' => $c->full_name,
                'phone' => $c->phone,
                'photo_path' => $c->photo_path,
                'photo_url' => $this->photoUrl($c->photo_path),
                'relation' => $c->relation,
                'can_entry' => $c->can_entry,
            ])->values()->all(),
            'entry_logs' => $guest->entryLogs->map(fn ($log) => [
                'id' => $log->id,
                'person_name' => $log->person_name,
                'direction' => $log->direction,
                'scanned_at' => $log->scanned_at->format('d M Y, h:i A'),
                'guest_companion_id' => $log->guest_companion_id,
            ])->values()->all(),
            'portal_login' => $this->portalLoginHint($guest),
        ];
    }

    public function portalLoginHint(Guest $guest): array
    {
        $guest->loadMissing('user');
        $loginId = $guest->email ?: $guest->guest_code;

        return [
            'login_id' => $loginId,
            'email' => $guest->user?->email,
            'can_login' => (bool) $guest->user_id,
            'hint' => $guest->email
                ? 'Login with email: '.$guest->email
                : 'Login with guest code: '.$guest->guest_code,
        ];
    }

    public function syncPortalUser(Guest $guest, ?string $password = null): User
    {
        $guest->loadMissing('user');
        $tenantId = $guest->tenant_id;
        $loginEmail = $guest->email
            ? strtolower(trim($guest->email))
            : strtolower($guest->guest_code).'@guest.littlestars.local';

        $user = $guest->user;
        if (! $user) {
            $user = User::query()->where('email', $loginEmail)->first();
        }

        if (! $user) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'name' => $guest->full_name,
                'email' => $loginEmail,
                'phone' => $guest->phone,
                'password' => Hash::make($password ?? 'password'),
                'is_active' => $guest->status === 'active',
            ]);
            $guest->update(['user_id' => $user->id]);
        } else {
            $user->update([
                'name' => $guest->full_name,
                'email' => $loginEmail,
                'phone' => $guest->phone,
                'is_active' => $guest->status === 'active',
            ]);
            if (! $guest->user_id) {
                $guest->update(['user_id' => $user->id]);
            }
        }

        $role = Role::query()->firstOrCreate(
            ['name' => 'guest'],
            ['label' => 'Guest'],
        );
        if (! $user->roles()->where('roles.id', $role->id)->exists()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        if ($password) {
            $user->update(['password' => Hash::make($password)]);
        }

        return $user->fresh();
    }

    /** @param array<int, array<string, mixed>> $companions */
    public function syncCompanions(Guest $guest, array $companions): void
    {
        $guest->companions()->delete();

        foreach ($companions as $i => $row) {
            if (empty($row['full_name'])) {
                continue;
            }
            $guest->companions()->create([
                'full_name' => $row['full_name'],
                'phone' => $row['phone'] ?? null,
                'photo_path' => $row['photo_path'] ?? null,
                'relation' => $row['relation'] ?? null,
                'can_entry' => $row['can_entry'] ?? true,
                'sort_order' => $i,
            ]);
        }
    }

    public function guestForUser(User $user): ?Guest
    {
        return Guest::query()->where('user_id', $user->id)->first();
    }

    public function resolveGuest(string $raw): ?Guest
    {
        return $this->scanResolver->findGuest($raw);
    }

    public function lastEntryForPerson(Guest $guest, ?int $companionId): ?GuestEntryLog
    {
        return GuestEntryLog::query()
            ->where('guest_id', $guest->id)
            ->when(
                $companionId,
                fn ($q) => $q->where('guest_companion_id', $companionId),
                fn ($q) => $q->whereNull('guest_companion_id'),
            )
            ->whereDate('scanned_at', today())
            ->latest('scanned_at')
            ->first();
    }

    public function suggestedDirection(Guest $guest, ?int $companionId): string
    {
        $last = $this->lastEntryForPerson($guest, $companionId);

        if (! $last || $last->direction === 'out') {
            return 'in';
        }

        return 'out';
    }

    public function recordEntry(Guest $guest, string $direction, ?int $companionId, ?int $userId, ?string $notes = null): GuestEntryLog
    {
        $personName = $guest->full_name;

        if ($companionId) {
            $companion = $guest->companions()->whereKey($companionId)->first();
            if (! $companion || ! $companion->can_entry) {
                throw new \InvalidArgumentException('Companion not authorized for entry');
            }
            $personName = $companion->full_name;
        }

        return GuestEntryLog::create([
            'guest_id' => $guest->id,
            'guest_companion_id' => $companionId,
            'person_name' => $personName,
            'direction' => $direction,
            'scanned_at' => now(),
            'scanned_by_user_id' => $userId,
            'notes' => $notes,
        ]);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(collect($parts)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join(''));
    }
}
