<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\IdCard;
use Illuminate\Support\Str;

class QrScanResolver
{
    public function generateScanCode(): string
    {
        // Letters + digits only — never looks like http:// or a web link
        return strtolower(Str::random(20));
    }

    /** Public scan landing path (staff login only — not encoded in QR). */
    public function scanUrl(string $scanCode): string
    {
        $base = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');

        return $base.'/s/'.$scanCode;
    }

    /** Opaque code only — no URL in QR (phone scanner must not open a link). */
    public function qrPayload(string $scanCode): string
    {
        return $scanCode;
    }

    /** Normalize camera / URL / legacy token input to a lookup key. */
    public function normalizeInput(string $raw): string
    {
        $value = trim($raw);
        if ($value === '') {
            return '';
        }

        if (preg_match('~/s/([A-Za-z0-9_-]+)(?:\?.*)?$~', $value, $matches)) {
            return $matches[1];
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = parse_url($value, PHP_URL_PATH) ?? '';
            if (preg_match('~/s/([A-Za-z0-9_-]+)$~', $path, $matches)) {
                return $matches[1];
            }
        }

        return $value;
    }

    public function findGuest(string $raw): ?Guest
    {
        $key = $this->normalizeInput($raw);
        if ($key === '') {
            return null;
        }

        return Guest::query()
            ->where(function ($q) use ($key) {
                $q->where('scan_code', $key)
                    ->orWhere('qr_token', $key)
                    ->orWhere('guest_code', $key);
            })
            ->first();
    }

    public function findIdCard(string $raw): ?IdCard
    {
        $key = $this->normalizeInput($raw);
        if ($key === '') {
            return null;
        }

        return IdCard::query()
            ->where(function ($q) use ($key) {
                $q->where('scan_code', $key)
                    ->orWhere('qr_token', $key)
                    ->orWhere('card_number', $key);
            })
            ->first();
    }
}
