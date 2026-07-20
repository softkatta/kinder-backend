<?php

namespace App\Services\Notifications;

use App\Models\IntegrationSetting;
use App\Models\Tenant;

class IntegrationSettingsService
{
    public function get(): IntegrationSetting
    {
        $tenant = Tenant::query()->first();

        return IntegrationSetting::query()->firstOrCreate(
            ['tenant_id' => $tenant?->id],
            $this->defaultsFromEnv(),
        );
    }

    /** @return array<string, mixed> */
    public function defaultsFromEnv(): array
    {
        return [
            'email_enabled' => (bool) env('MAIL_HOST'),
            'email_mailer' => env('MAIL_MAILER', 'smtp'),
            'email_host' => env('MAIL_HOST'),
            'email_port' => env('MAIL_PORT') ? (int) env('MAIL_PORT') : 587,
            'email_username' => env('MAIL_USERNAME'),
            'email_password' => env('MAIL_PASSWORD'),
            'email_encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'email_from_address' => env('MAIL_FROM_ADDRESS'),
            'email_from_name' => env('MAIL_FROM_NAME', env('APP_NAME')),
            'broadcast_enabled' => filter_var(env('REVERB_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'broadcast_driver' => env('BROADCAST_CONNECTION', 'reverb'),
            'broadcast_app_id' => env('REVERB_APP_ID') ?: env('PUSHER_APP_ID'),
            'broadcast_key' => env('REVERB_APP_KEY') ?: env('PUSHER_APP_KEY'),
            'broadcast_secret' => env('REVERB_APP_SECRET') ?: env('PUSHER_APP_SECRET'),
            'broadcast_cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'broadcast_host' => env('REVERB_HOST', 'localhost'),
            'broadcast_port' => (int) (env('REVERB_PORT') ?: 8080),
            'broadcast_scheme' => env('REVERB_SCHEME', 'http'),
            'livekit_enabled' => filled(env('LIVEKIT_URL')),
            'livekit_url' => env('LIVEKIT_URL'),
            'livekit_api_key' => env('LIVEKIT_API_KEY', 'devkey'),
            'livekit_api_secret' => env('LIVEKIT_API_SECRET'),
        ];
    }

    /** @return array<string, mixed> */
    public function toApiPayload(?IntegrationSetting $settings = null): array
    {
        $settings ??= $this->get();

        return [
            'email' => [
                'enabled' => $settings->email_enabled,
                'mailer' => $settings->email_mailer,
                'host' => $settings->email_host,
                'port' => $settings->email_port,
                'username' => $settings->email_username,
                'password_set' => filled($settings->email_password),
                'encryption' => $settings->email_encryption,
                'from_address' => $settings->email_from_address,
                'from_name' => $settings->email_from_name,
            ],
            'whatsapp' => [
                'enabled' => $settings->whatsapp_enabled,
                'provider' => $settings->whatsapp_provider,
                'account_sid' => $settings->whatsapp_account_sid,
                'auth_token_set' => filled($settings->whatsapp_auth_token),
                'from_number' => $settings->whatsapp_from_number,
                'phone_number_id' => $settings->whatsapp_phone_number_id,
                'access_token_set' => filled($settings->whatsapp_access_token),
            ],
            'broadcast' => [
                'enabled' => $settings->broadcast_enabled,
                'driver' => $settings->broadcast_driver,
                'app_id' => $settings->broadcast_app_id,
                'key' => $settings->broadcast_key,
                'secret_set' => filled($settings->broadcast_secret),
                'cluster' => $settings->broadcast_cluster,
                'host' => $settings->broadcast_host,
                'port' => $settings->broadcast_port,
                'scheme' => $settings->broadcast_scheme,
            ],
            'livekit' => [
                'enabled' => (bool) $settings->livekit_enabled,
                'url' => $settings->livekit_url,
                'api_key' => $settings->livekit_api_key,
                'api_secret_set' => filled($settings->livekit_api_secret),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function publicBroadcastConfig(): array
    {
        $settings = $this->get();
        if (! $settings->broadcast_enabled || ! $settings->broadcast_key) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'driver' => $settings->broadcast_driver,
            'key' => $settings->broadcast_key,
            'cluster' => $settings->broadcast_cluster,
            'host' => $settings->broadcast_host,
            'port' => $settings->broadcast_port,
            'scheme' => $settings->broadcast_scheme,
        ];
    }

    /** @param array<string, mixed> $email */
    public function updateEmail(array $email, IntegrationSetting $settings): void
    {
        $update = [];
        foreach (['enabled', 'mailer', 'host', 'port', 'username', 'encryption', 'from_address', 'from_name'] as $field) {
            $key = $field === 'enabled' ? 'email_enabled' : 'email_'.$field;
            if (array_key_exists($field, $email)) {
                $update[$key] = $field === 'enabled' ? (bool) $email[$field] : $email[$field];
            }
        }
        if (! empty($email['password'])) {
            $update['email_password'] = $email['password'];
        }
        $settings->update($update);
    }

    /** @param array<string, mixed> $whatsapp */
    public function updateWhatsapp(array $whatsapp, IntegrationSetting $settings): void
    {
        $map = [
            'enabled' => 'whatsapp_enabled',
            'provider' => 'whatsapp_provider',
            'account_sid' => 'whatsapp_account_sid',
            'from_number' => 'whatsapp_from_number',
            'phone_number_id' => 'whatsapp_phone_number_id',
        ];
        $update = [];
        foreach ($map as $in => $col) {
            if (array_key_exists($in, $whatsapp)) {
                $update[$col] = $in === 'enabled' ? (bool) $whatsapp[$in] : $whatsapp[$in];
            }
        }
        if (! empty($whatsapp['auth_token'])) {
            $update['whatsapp_auth_token'] = $whatsapp['auth_token'];
        }
        if (! empty($whatsapp['access_token'])) {
            $update['whatsapp_access_token'] = $whatsapp['access_token'];
        }
        $settings->update($update);
    }

    /** @param array<string, mixed> $broadcast */
    public function updateBroadcast(array $broadcast, IntegrationSetting $settings): void
    {
        $map = [
            'enabled' => 'broadcast_enabled',
            'driver' => 'broadcast_driver',
            'app_id' => 'broadcast_app_id',
            'key' => 'broadcast_key',
            'cluster' => 'broadcast_cluster',
            'host' => 'broadcast_host',
            'port' => 'broadcast_port',
            'scheme' => 'broadcast_scheme',
        ];
        $update = [];
        foreach ($map as $in => $col) {
            if (array_key_exists($in, $broadcast)) {
                $update[$col] = $in === 'enabled' ? (bool) $broadcast[$in] : $broadcast[$in];
            }
        }
        if (! empty($broadcast['secret'])) {
            $update['broadcast_secret'] = $broadcast['secret'];
        }
        $settings->update($update);
    }

    /** @param array<string, mixed> $livekit */
    public function updateLivekit(array $livekit, IntegrationSetting $settings): void
    {
        $update = [];
        if (array_key_exists('enabled', $livekit)) {
            $update['livekit_enabled'] = (bool) $livekit['enabled'];
        }
        if (array_key_exists('url', $livekit)) {
            $update['livekit_url'] = $livekit['url'];
        }
        if (array_key_exists('api_key', $livekit)) {
            $update['livekit_api_key'] = $livekit['api_key'];
        }
        if (! empty($livekit['api_secret'])) {
            $update['livekit_api_secret'] = $livekit['api_secret'];
        }
        if ($update !== []) {
            $settings->update($update);
        }
    }

    public function applyLivekitConfig(?IntegrationSetting $settings = null): void
    {
        $settings ??= $this->get();

        if (! $settings->livekit_enabled) {
            config([
                'livekit.url' => '',
                'livekit.api_key' => '',
                'livekit.api_secret' => '',
            ]);

            return;
        }

        $url = trim((string) ($settings->livekit_url ?? ''));
        $apiKey = trim((string) ($settings->livekit_api_key ?? ''));
        $apiSecret = (string) ($settings->livekit_api_secret ?? '');

        if ($url === '' || $apiKey === '' || $apiSecret === '') {
            config([
                'livekit.url' => '',
                'livekit.api_key' => '',
                'livekit.api_secret' => '',
            ]);

            return;
        }

        config([
            'livekit.url' => $url,
            'livekit.api_key' => $apiKey,
            'livekit.api_secret' => $apiSecret,
        ]);
    }

    public function applyMailConfig(?IntegrationSetting $settings = null): void
    {
        $settings ??= $this->get();
        if (! $settings->email_enabled) {
            return;
        }

        config([
            'mail.default' => $settings->email_mailer ?: 'smtp',
            'mail.mailers.smtp.host' => $settings->email_host,
            'mail.mailers.smtp.port' => $settings->email_port ?? 587,
            'mail.mailers.smtp.username' => $settings->email_username,
            'mail.mailers.smtp.password' => $settings->email_password,
            'mail.mailers.smtp.encryption' => $settings->email_encryption,
            'mail.from.address' => $settings->email_from_address ?: config('mail.from.address'),
            'mail.from.name' => $settings->email_from_name ?: config('mail.from.name'),
        ]);
    }

    public function applyBroadcastConfig(?IntegrationSetting $settings = null): void
    {
        $settings ??= $this->get();
        if (! $settings->broadcast_enabled) {
            return;
        }

        $driver = $settings->broadcast_driver ?: 'reverb';
        config(['broadcasting.default' => $driver]);

        if ($driver === 'reverb') {
            config([
                'broadcasting.connections.reverb.key' => $settings->broadcast_key,
                'broadcasting.connections.reverb.secret' => $settings->broadcast_secret,
                'broadcasting.connections.reverb.app_id' => $settings->broadcast_app_id,
                'broadcasting.connections.reverb.options.host' => $settings->broadcast_host,
                'broadcasting.connections.reverb.options.port' => $settings->broadcast_port,
                'broadcasting.connections.reverb.options.scheme' => $settings->broadcast_scheme,
            ]);
        }

        if ($driver === 'pusher') {
            config([
                'broadcasting.connections.pusher.key' => $settings->broadcast_key,
                'broadcasting.connections.pusher.secret' => $settings->broadcast_secret,
                'broadcasting.connections.pusher.app_id' => $settings->broadcast_app_id,
                'broadcasting.connections.pusher.options.cluster' => $settings->broadcast_cluster,
                'broadcasting.connections.pusher.options.host' => $settings->broadcast_host,
                'broadcasting.connections.pusher.options.port' => $settings->broadcast_port,
                'broadcasting.connections.pusher.options.scheme' => $settings->broadcast_scheme,
                'broadcasting.connections.pusher.options.useTLS' => $settings->broadcast_scheme === 'https',
            ]);
        }
    }
}
