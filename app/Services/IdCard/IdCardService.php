<?php

namespace App\Services\IdCard;

use App\Models\CmsItem;
use App\Models\IdCard;
use App\Models\Tenant;
use App\Services\QrScanResolver;
use Illuminate\Support\Str;

class IdCardService
{
    public function __construct(
        private readonly QrCodeGenerator $qr,
        private readonly QrScanResolver $scanResolver,
    ) {}

    public function generateCardNumber(string $type): string
    {
        $prefix = match ($type) {
            'student' => 'STU',
            'teacher' => 'TCH',
            'staff' => 'STF',
            'parent' => 'PAR',
            'guest' => 'GST',
            default => 'IDC',
        };

        return $prefix.'-'.strtoupper(Str::random(8));
    }

    public function generateQrToken(): string
    {
        return 'LS-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(12));
    }

    public function ensureScanCode(IdCard $card): IdCard
    {
        if ($card->scan_code) {
            return $card;
        }

        $card->update(['scan_code' => $this->scanResolver->generateScanCode()]);

        return $card->fresh();
    }

    public function schoolProfile(): array
    {
        $profile = CmsItem::query()
            ->where('type', 'school_profile')
            ->where('status', 'published')
            ->first();

        $meta = $profile?->meta ?? [];
        $address = trim(implode(', ', array_filter([
            $meta['address'] ?? null,
            $meta['city'] ?? null,
            $meta['state'] ?? null,
            $meta['pincode'] ?? null,
        ])));

        return [
            'name' => $meta['school_name'] ?? $profile?->title ?? 'Little Stars Kindergarten',
            'short_name' => $meta['short_name'] ?? 'Little Stars',
            'logo_path' => $profile?->image,
            'address' => $address !== '' ? $address : ($meta['address'] ?? '123 Sunshine Lane, Pune, Maharashtra 411001'),
            'phone' => $meta['phone'] ?? '+91 98765 43210',
            'email' => $meta['email'] ?? 'info@littlestars.com',
            'website' => $meta['website'] ?? 'www.littlestars.com',
            'summary' => $profile?->summary ?? ($meta['tagline'] ?? null),
            'principal_name' => $meta['principal_name'] ?? null,
            'principal_signature' => $meta['principal_signature'] ?? null,
            'school_seal' => $meta['school_seal'] ?? $meta['seal_path'] ?? null,
            'udis_number' => $meta['udis_number'] ?? $meta['udise_number'] ?? null,
            'established_year' => $meta['established_year'] ?? null,
        ];
    }

    public function toCardViewData(IdCard $card): array
    {
        $school = $this->schoolProfile();
        $meta = $card->meta ?? [];
        $theme = $this->themeForType($card->card_type);
        $card = $this->ensureScanCode($card);
        $qrPayload = $this->scanResolver->qrPayload($card->scan_code);

        return [
            'id' => $card->id,
            'card_type' => $card->card_type,
            'card_number' => $card->card_number,
            'qr_token' => $card->qr_token,
            'status' => $card->status,
            'full_name' => $card->full_name,
            'photo_path' => $card->photo_path,
            'photo_url' => $this->photoUrl($card->photo_path),
            'blood_group' => $card->blood_group,
            'academic_year' => $card->academic_year,
            'issue_date' => $card->issue_date->format('d M Y'),
            'expiry_date' => $card->expiry_date->format('d M Y'),
            'issue_date_raw' => $card->issue_date->toDateString(),
            'expiry_date_raw' => $card->expiry_date->toDateString(),
            'emergency_contact' => $card->emergency_contact,
            'role_label' => $card->roleLabel(),
            'role_badge' => strtoupper($card->roleLabel()),
            'meta' => $meta,
            'school' => $school,
            'theme' => $theme,
            'qr_svg' => $this->qr->svg($qrPayload),
            'qr_data_uri' => $this->qr->dataUri($qrPayload),
            'initials' => $this->initials($card->full_name),
            'validity_label' => $this->validityLabel($card),
            'subtitle_lines' => $this->subtitleLines($card),
            'back_note' => 'This card is the property of the school. If found, please return it to the school office.',
        ];
    }

    public function themeForType(string $type): array
    {
        return match ($type) {
            'student' => [
                'gradient_start' => '#312E81',
                'gradient_mid' => '#4F46E5',
                'gradient_end' => '#7C3AED',
                'accent' => '#818CF8',
                'badge_bg' => 'rgba(255,255,255,0.22)',
                'badge_text' => '#FFFFFF',
            ],
            'teacher' => [
                'gradient_start' => '#064E3B',
                'gradient_mid' => '#059669',
                'gradient_end' => '#0D9488',
                'accent' => '#34D399',
                'badge_bg' => 'rgba(255,255,255,0.22)',
                'badge_text' => '#FFFFFF',
            ],
            'staff' => [
                'gradient_start' => '#78350F',
                'gradient_mid' => '#D97706',
                'gradient_end' => '#B45309',
                'accent' => '#FBBF24',
                'badge_bg' => 'rgba(255,255,255,0.22)',
                'badge_text' => '#FFFFFF',
            ],
            'parent' => [
                'gradient_start' => '#581C87',
                'gradient_mid' => '#7C3AED',
                'gradient_end' => '#DB2777',
                'accent' => '#E879F9',
                'badge_bg' => 'rgba(255,255,255,0.22)',
                'badge_text' => '#FFFFFF',
            ],
            'guest' => [
                'gradient_start' => '#134E4A',
                'gradient_mid' => '#0F766E',
                'gradient_end' => '#059669',
                'accent' => '#2DD4BF',
                'badge_bg' => 'rgba(255,255,255,0.22)',
                'badge_text' => '#FFFFFF',
            ],
            default => [
                'gradient_start' => '#134E4A',
                'gradient_mid' => '#0F766E',
                'gradient_end' => '#059669',
                'accent' => '#2DD4BF',
                'badge_bg' => 'rgba(255,255,255,0.22)',
                'badge_text' => '#FFFFFF',
            ],
        };
    }

    private function photoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(collect($parts)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    }

    private function validityLabel(IdCard $card): string
    {
        if ($card->card_type === 'guest') {
            $from = $card->meta['valid_from'] ?? $card->issue_date->format('M Y');
            $until = $card->meta['valid_until'] ?? $card->expiry_date->format('M Y');

            return "{$from} – {$until}";
        }

        return ($card->academic_year ?? $card->issue_date->format('Y')).' · Valid till '.$card->expiry_date->format('M Y');
    }

    private function subtitleLines(IdCard $card): array
    {
        $m = $card->meta ?? [];

        return match ($card->card_type) {
            'student' => array_filter([
                isset($m['class_name'], $m['section_name']) ? "{$m['class_name']} · {$m['section_name']}" : ($m['class_name'] ?? null),
                $m['parent_name'] ?? null,
                $m['roll_number'] ? 'Roll: '.$m['roll_number'] : null,
            ]),
            'teacher', 'staff' => array_filter([
                $m['designation'] ?? null,
                $m['department'] ?? null,
            ]),
            'parent' => array_filter([
                $m['relationship'] ?? null,
                isset($m['student_names']) ? 'Parent of: '.$m['student_names'] : null,
            ]),
            'guest' => array_filter([
                $m['purpose'] ?? null,
                $m['company'] ?? null,
            ]),
            default => [],
        };
    }

    public function create(array $data): IdCard
    {
        $tenant = Tenant::query()->first();

        return IdCard::create([
            'tenant_id' => $tenant?->id,
            'card_type' => $data['card_type'],
            'card_number' => $data['card_number'] ?? $this->generateCardNumber($data['card_type']),
            'qr_token' => $data['qr_token'] ?? $this->generateQrToken(),
            'scan_code' => $data['scan_code'] ?? $this->scanResolver->generateScanCode(),
            'status' => $data['status'] ?? 'active',
            'full_name' => $data['full_name'],
            'photo_path' => $data['photo_path'] ?? null,
            'blood_group' => $data['blood_group'] ?? null,
            'academic_year' => $data['academic_year'] ?? date('Y').'-'.(date('Y') + 1),
            'issue_date' => $data['issue_date'] ?? now()->toDateString(),
            'expiry_date' => $data['expiry_date'] ?? now()->addYear()->toDateString(),
            'emergency_contact' => $data['emergency_contact'] ?? null,
            'meta' => $data['meta'] ?? [],
            'user_id' => $data['user_id'] ?? null,
        ]);
    }
}
