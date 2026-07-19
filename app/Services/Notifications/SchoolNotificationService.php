<?php

namespace App\Services\Notifications;

use App\Events\AdminAlertBroadcast;
use App\Models\CmsItem;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SchoolNotificationService
{
    public const EVENT_NEW_ADMISSION = 'new_admission';

    public const EVENT_FEE_PAYMENT = 'fee_payment';

    public const EVENT_ATTENDANCE_SUMMARY = 'attendance_summary';

    public const EVENT_JOB_APPLICATION = 'job_application';

    public const EVENT_CONTACT_INQUIRY = 'contact_inquiry';

    public function __construct(
        private readonly IntegrationSettingsService $integrations,
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function notifyAdmins(string $eventKey, string $title, string $body, array $data = []): void
    {
        $prefs = $this->eventPreferences($eventKey);
        if (! ($prefs['enabled'] ?? true)) {
            return;
        }

        $channels = $prefs['channels'] ?? ['email' => true, 'whatsapp' => false, 'push' => true];
        $settings = $this->integrations->get();
        $this->integrations->applyMailConfig($settings);
        $this->integrations->applyBroadcastConfig($settings);

        $admins = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin']))
            ->get();

        foreach ($admins as $admin) {
            if ($channels['push'] ?? false) {
                UserNotification::create([
                    'user_id' => $admin->id,
                    'type' => $eventKey,
                    'title' => $title,
                    'body' => $body,
                    'data' => array_merge($data, ['channels' => array_keys(array_filter($channels))]),
                ]);

                if ($settings->broadcast_enabled) {
                    try {
                        broadcast(new AdminAlertBroadcast($admin->id, $title, $body, $eventKey, $data));
                    } catch (\Throwable $e) {
                        Log::warning('Admin broadcast failed', ['error' => $e->getMessage()]);
                    }
                }
            }

            if (($channels['email'] ?? false) && $settings->email_enabled && $admin->email) {
                try {
                    Mail::raw($body, function ($message) use ($admin, $title, $settings) {
                        $message->to($admin->email)
                            ->subject($title)
                            ->from(
                                $settings->email_from_address ?: config('mail.from.address'),
                                $settings->email_from_name ?: config('mail.from.name'),
                            );
                    });
                } catch (\Throwable $e) {
                    Log::warning('Admin email failed', ['user' => $admin->id, 'error' => $e->getMessage()]);
                }
            }

            if (($channels['whatsapp'] ?? false) && $settings->whatsapp_enabled && $admin->phone) {
                try {
                    $this->whatsapp->send($admin->phone, "*{$title}*\n\n{$body}", $settings);
                } catch (\Throwable $e) {
                    Log::warning('Admin WhatsApp failed', ['user' => $admin->id, 'error' => $e->getMessage()]);
                }
            }
        }
    }

    /** @return array{enabled: bool, channels: array<string, bool>} */
    private function eventPreferences(string $eventKey): array
    {
        $profile = CmsItem::query()->where('type', 'school_profile')->first();
        $meta = is_array($profile?->meta) ? $profile->meta : [];
        $rows = $meta['notifications'] ?? [];

        foreach ($rows as $row) {
            if (($row['key'] ?? null) === $eventKey || $this->legacyKey($row['event'] ?? '') === $eventKey) {
                return [
                    'enabled' => (bool) ($row['enabled'] ?? true),
                    'channels' => is_array($row['channels'] ?? null) ? $row['channels'] : [
                        'email' => true,
                        'whatsapp' => str_contains(strtolower($row['channel'] ?? ''), 'sms') || str_contains(strtolower($row['channel'] ?? ''), 'whatsapp'),
                        'push' => true,
                    ],
                ];
            }
        }

        return ['enabled' => true, 'channels' => ['email' => true, 'whatsapp' => false, 'push' => true]];
    }

    private function legacyKey(string $event): string
    {
        return match ($event) {
            'New admission applications' => self::EVENT_NEW_ADMISSION,
            'Fee payment received' => self::EVENT_FEE_PAYMENT,
            'Daily attendance summary' => self::EVENT_ATTENDANCE_SUMMARY,
            'Job applications' => self::EVENT_JOB_APPLICATION,
            'Contact form inquiries' => self::EVENT_CONTACT_INQUIRY,
            default => strtolower(str_replace(' ', '_', $event)),
        };
    }
}
