<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'email_enabled',
        'email_mailer',
        'email_host',
        'email_port',
        'email_username',
        'email_password',
        'email_encryption',
        'email_from_address',
        'email_from_name',
        'whatsapp_enabled',
        'whatsapp_provider',
        'whatsapp_account_sid',
        'whatsapp_auth_token',
        'whatsapp_from_number',
        'whatsapp_phone_number_id',
        'whatsapp_access_token',
        'broadcast_enabled',
        'broadcast_driver',
        'broadcast_app_id',
        'broadcast_key',
        'broadcast_secret',
        'broadcast_cluster',
        'broadcast_host',
        'broadcast_port',
        'broadcast_scheme',
        'livekit_enabled',
        'livekit_url',
        'livekit_api_key',
        'livekit_api_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'broadcast_enabled' => 'boolean',
            'livekit_enabled' => 'boolean',
            'email_password' => 'encrypted',
            'whatsapp_auth_token' => 'encrypted',
            'whatsapp_access_token' => 'encrypted',
            'broadcast_secret' => 'encrypted',
            'livekit_api_secret' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
