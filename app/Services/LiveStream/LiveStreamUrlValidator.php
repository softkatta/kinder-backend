<?php

namespace App\Services\LiveStream;

use Illuminate\Validation\ValidationException;

class LiveStreamUrlValidator
{
    public function validate(string $streamType, string $url): void
    {
        if ($streamType === 'builtin_camera') {
            return;
        }

        $url = trim($url);
        if ($url === '') {
            throw ValidationException::withMessages(['stream_url' => 'Stream URL is required.']);
        }

        if (! filter_var($url, FILTER_VALIDATE_URL) && ! $this->isBareId($streamType, $url)) {
            throw ValidationException::withMessages(['stream_url' => 'Invalid stream URL format.']);
        }

        $ok = match ($streamType) {
            'youtube' => $this->isYoutube($url),
            'vimeo' => $this->isVimeo($url),
            'hls' => str_contains($url, '.m3u8') || str_ends_with($url, '.m3u8'),
            'facebook' => str_contains($url, 'facebook.com') || str_contains($url, 'fb.watch'),
            'embed', 'rtmp' => filter_var($url, FILTER_VALIDATE_URL) !== false,
            default => filter_var($url, FILTER_VALIDATE_URL) !== false,
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'stream_url' => "URL does not match stream type ({$streamType}).",
            ]);
        }
    }

    public function mapSourceToStreamType(string $source): string
    {
        return match ($source) {
            'youtube', 'mobile_camera' => 'youtube',
            'builtin_camera' => 'builtin_camera',
            'vimeo' => 'vimeo',
            'facebook' => 'facebook',
            'hls', 'obs', 'rtmp', 'external_camera' => 'hls',
            default => 'embed',
        };
    }

    private function isBareId(string $type, string $value): bool
    {
        return in_array($type, ['youtube', 'vimeo'], true) && ! str_contains($value, '/');
    }

    private function isYoutube(string $url): bool
    {
        return (bool) preg_match('/(?:youtu\.be\/|youtube\.com\/)/', $url)
            || (! str_contains($url, '/') && strlen($url) <= 20);
    }

    private function isVimeo(string $url): bool
    {
        return (bool) preg_match('/vimeo\.com\//', $url) || ctype_digit($url);
    }
}
