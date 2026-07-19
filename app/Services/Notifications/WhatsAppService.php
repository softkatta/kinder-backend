<?php

namespace App\Services\Notifications;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function __construct(
        private readonly IntegrationSettingsService $settings,
    ) {}

    public function send(string $to, string $message, ?IntegrationSetting $config = null): bool
    {
        $config ??= $this->settings->get();
        if (! $config->whatsapp_enabled) {
            return false;
        }

        $to = $this->normalizePhone($to);

        return match ($config->whatsapp_provider) {
            'meta' => $this->sendViaMeta($config, $to, $message),
            default => $this->sendViaTwilio($config, $to, $message),
        };
    }

    private function sendViaTwilio(IntegrationSetting $config, string $to, string $message): bool
    {
        if (! $config->whatsapp_account_sid || ! $config->whatsapp_auth_token || ! $config->whatsapp_from_number) {
            throw new \RuntimeException('Twilio WhatsApp credentials are incomplete.');
        }

        $from = str_starts_with($config->whatsapp_from_number, 'whatsapp:')
            ? $config->whatsapp_from_number
            : 'whatsapp:'.$config->whatsapp_from_number;

        $toAddress = str_starts_with($to, 'whatsapp:') ? $to : 'whatsapp:'.$to;

        $response = Http::withBasicAuth($config->whatsapp_account_sid, $config->whatsapp_auth_token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$config->whatsapp_account_sid}/Messages.json", [
                'From' => $from,
                'To' => $toAddress,
                'Body' => $message,
            ]);

        if (! $response->successful()) {
            Log::warning('Twilio WhatsApp failed', ['body' => $response->body()]);
            throw new \RuntimeException('WhatsApp send failed: '.$response->json('message', 'Unknown error'));
        }

        return true;
    }

    private function sendViaMeta(IntegrationSetting $config, string $to, string $message): bool
    {
        if (! $config->whatsapp_phone_number_id || ! $config->whatsapp_access_token) {
            throw new \RuntimeException('Meta WhatsApp credentials are incomplete.');
        }

        $phone = preg_replace('/\D+/', '', $to) ?: $to;

        $response = Http::withToken($config->whatsapp_access_token)
            ->post("https://graph.facebook.com/v19.0/{$config->whatsapp_phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        if (! $response->successful()) {
            Log::warning('Meta WhatsApp failed', ['body' => $response->body()]);
            throw new \RuntimeException('WhatsApp send failed: '.$response->json('error.message', 'Unknown error'));
        }

        return true;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: $phone;

        return str_starts_with($digits, '91') ? '+'.$digits : '+91'.$digits;
    }
}
