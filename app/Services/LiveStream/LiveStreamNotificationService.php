<?php

namespace App\Services\LiveStream;

use App\Models\LiveStream;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notifications\IntegrationSettingsService;
use App\Services\Notifications\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LiveStreamNotificationService
{
    public const TYPE_SCHEDULED = 'live_scheduled';

    public const TYPE_REMINDER = 'live_reminder';

    public const TYPE_STARTED = 'live_started';

    public const TYPE_ENDED = 'live_ended';

    public function __construct(
        private readonly IntegrationSettingsService $integrations,
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function notifyParents(LiveStream $stream, string $type, string $title, string $body): void
    {
        $settings = $this->integrations->get();
        $this->integrations->applyMailConfig($settings);

        $parents = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'parent'))
            ->where('is_active', true)
            ->get();

        foreach ($parents as $parent) {
            UserNotification::create([
                'user_id' => $parent->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => [
                    'live_stream_id' => $stream->id,
                    'stream_title' => $stream->title,
                    'channels' => ['in_app', 'email', 'whatsapp'],
                ],
            ]);

            if ($settings->email_enabled && $parent->email) {
                try {
                    Mail::raw($body, function ($message) use ($parent, $title, $settings) {
                        $message->to($parent->email)
                            ->subject($title)
                            ->from(
                                $settings->email_from_address ?: config('mail.from.address'),
                                $settings->email_from_name ?: config('mail.from.name'),
                            );
                    });
                } catch (\Throwable $e) {
                    Log::warning('Live stream email failed', ['user' => $parent->id, 'error' => $e->getMessage()]);
                }
            }

            if ($settings->whatsapp_enabled && $parent->phone) {
                try {
                    $this->whatsapp->send($parent->phone, "*{$title}*\n\n{$body}", $settings);
                } catch (\Throwable $e) {
                    Log::warning('Live stream WhatsApp failed', ['user' => $parent->id, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    public function markSent(LiveStream $stream, string $key): void
    {
        $sent = $stream->notifications_sent ?? [];
        $sent[$key] = now()->toIso8601String();
        $stream->update(['notifications_sent' => $sent]);
    }

    public function wasSent(LiveStream $stream, string $key): bool
    {
        return isset(($stream->notifications_sent ?? [])[$key]);
    }
}
