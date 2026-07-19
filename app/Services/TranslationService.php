<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    /** Meta keys that must never be auto-translated. */
    private const SKIP_META_KEYS = [
        'grade_level',
        'icon',
        'featured',
        'date',
        'application_deadline',
        'employment_type',
        'readTime',
        'category',
        'album',
        'email',
        'phone',
        'address',
        'city',
        'established_year',
        'school_name',
        'short_name',
        'principal_name',
        'slug',
    ];

    public function translate(?string $text, string $target = 'mr', string $source = 'en'): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        if ($target === $source) {
            return $text;
        }

        if ($this->shouldSkipString($text)) {
            return $text;
        }

        if (str_contains($text, '<') && str_contains($text, '>')) {
            return $text;
        }

        $cacheKey = 'cms_translate:'.$source.':'.$target.':'.hash('sha256', $text);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($text, $source, $target) {
            return $this->callGoogleTranslate($text, $source, $target)
                ?? $this->callMyMemoryTranslate($text, $source, $target)
                ?? $text;
        });
    }

    public function shouldSkipMetaKey(string $key): bool
    {
        return in_array($key, self::SKIP_META_KEYS, true)
            || str_ends_with($key, '_mr')
            || str_ends_with($key, '_en');
    }

    /** @return mixed */
    public function translateValue(mixed $value, string $locale, ?string $key = null): mixed
    {
        if ($locale !== 'mr') {
            return $value;
        }

        if ($key !== null && $this->shouldSkipMetaKey($key)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->translate($value) ?? $value;
        }

        if (is_array($value)) {
            return array_map(function ($item) use ($locale) {
                return is_string($item) ? ($this->translate($item) ?? $item) : $item;
            }, $value);
        }

        return $value;
    }

    private function shouldSkipString(string $text): bool
    {
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        if (preg_match('/^https?:\/\//i', $text)) {
            return true;
        }

        if (preg_match('/^\+?[\d\s\-()]{7,}$/', $text)) {
            return true;
        }

        return false;
    }

    private function callGoogleTranslate(string $text, string $source, string $target): ?string
    {
        try {
            $response = Http::timeout(12)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get('https://translate.googleapis.com/translate_a/single', [
                    'client' => 'gtx',
                    'sl' => $source,
                    'tl' => $target,
                    'dt' => 't',
                    'q' => $text,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            if (! is_array($data) || ! isset($data[0]) || ! is_array($data[0])) {
                return null;
            }

            $parts = [];
            foreach ($data[0] as $segment) {
                if (is_array($segment) && isset($segment[0]) && is_string($segment[0])) {
                    $parts[] = $segment[0];
                }
            }

            $translated = trim(implode('', $parts));

            return $translated !== '' ? $translated : null;
        } catch (\Throwable $e) {
            Log::warning('CMS auto-translation failed: '.$e->getMessage());

            return null;
        }
    }

    private function callMyMemoryTranslate(string $text, string $source, string $target): ?string
    {
        try {
            $response = Http::timeout(12)->get('https://api.mymemory.translated.net/get', [
                'q' => $text,
                'langpair' => $source.'|'.$target,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $translated = $response->json('responseData.translatedText');
            if (! is_string($translated) || trim($translated) === '') {
                return null;
            }

            $translated = trim($translated);
            if (strcasecmp($translated, $text) === 0) {
                return null;
            }

            return $translated;
        } catch (\Throwable $e) {
            Log::warning('MyMemory translation failed: '.$e->getMessage());

            return null;
        }
    }
}
