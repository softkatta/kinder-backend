<?php

namespace App\Services\LiveStream;

use App\Models\LiveStream;
use App\Models\LiveStreamCamera;
use Illuminate\Validation\ValidationException;

class LiveKitService
{
    public function isConfigured(): bool
    {
        $url = (string) config('livekit.url');

        return $url !== '' && config('livekit.api_key') && config('livekit.api_secret');
    }

    public function roomName(LiveStream $stream): string
    {
        return 'ks-live-'.$stream->id;
    }

    public function participantIdentity(LiveStreamCamera $camera): string
    {
        return 'camera-'.$camera->id;
    }

    public function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'livekit' => 'Built-in camera streaming is not configured. Set LIVEKIT_URL, LIVEKIT_API_KEY, and LIVEKIT_API_SECRET in .env and start the LiveKit server.',
            ]);
        }
    }

    public function createPublisherToken(LiveStream $stream, LiveStreamCamera $camera, string $staffName): string
    {
        $this->ensureConfigured();

        return $this->createToken(
            $this->roomName($stream),
            $this->participantIdentity($camera),
            $staffName,
            canPublish: true,
        );
    }

    public function createViewerToken(LiveStream $stream, string $viewerIdentity): string
    {
        $this->ensureConfigured();

        return $this->createToken(
            $this->roomName($stream),
            $viewerIdentity,
            'Viewer',
            canPublish: false,
        );
    }

    private function createToken(string $room, string $identity, string $name, bool $canPublish): string
    {
        $apiKey = (string) config('livekit.api_key');
        $apiSecret = (string) config('livekit.api_secret');
        $ttl = (int) config('livekit.token_ttl', 3600);
        $now = time();

        $payload = [
            'iss' => $apiKey,
            'sub' => $identity,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'name' => $name,
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canPublish' => $canPublish,
                'canSubscribe' => true,
                'canPublishData' => false,
            ],
        ];

        return $this->encodeJwt($payload, $apiSecret);
    }

    /** @param array<string, mixed> $payload */
    private function encodeJwt(array $payload, string $secret): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

        return "{$header}.{$body}.{$signature}";
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function publicConfig(): array
    {
        $configured = $this->isConfigured();

        return [
            'configured' => $configured,
            'url' => $configured ? (string) config('livekit.url') : null,
            'reachable' => $configured ? $this->isReachable() : false,
        ];
    }

    public function isReachable(): bool
    {
        $url = (string) config('livekit.url');
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? 'localhost';
        $port = (int) ($parts['port'] ?? ($parts['scheme'] === 'wss' ? 443 : 7880));

        $socket = @fsockopen($host, $port, $errno, $errstr, 1.5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
