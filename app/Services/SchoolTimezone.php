<?php

namespace App\Services;

use App\Models\CmsItem;
use DateTimeZone;

class SchoolTimezone
{
    private ?string $resolved = null;

    public function get(): string
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $fallback = (string) config('app.timezone', 'Asia/Kolkata');
        $profile = CmsItem::query()
            ->where('type', 'school_profile')
            ->where('slug', 'profile')
            ->first();

        $meta = is_array($profile?->meta) ? $profile->meta : [];
        $tz = trim((string) ($meta['timezone'] ?? ''));

        if ($tz === '' || ! $this->isValid($tz)) {
            $tz = $this->isValid($fallback) ? $fallback : 'Asia/Kolkata';
        }

        return $this->resolved = $tz;
    }

    public function apply(): void
    {
        $tz = $this->get();
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);
    }

    public function isValid(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /** Clear request cache after admin saves a new timezone. */
    public function forget(): void
    {
        $this->resolved = null;
    }
}
