<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamCamera extends Model
{
    public const TYPE_HLS = 'hls';

    public const TYPE_YOUTUBE = 'youtube';

    public const TYPE_VIMEO = 'vimeo';

    public const TYPE_EMBED = 'embed';

    public const TYPE_FACEBOOK = 'facebook';

    public const TYPE_RTMP = 'rtmp';

    public const TYPE_BUILTIN = 'builtin_camera';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_CONNECTING = 'connecting';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_READY = 'ready';

    public const STATUS_LIVE = 'live';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_OFFLINE = 'offline';

    /** @return list<string> */
    public static function connectionStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_CONNECTING,
            self::STATUS_CONNECTED,
            self::STATUS_READY,
            self::STATUS_LIVE,
            self::STATUS_DISCONNECTED,
            self::STATUS_OFFLINE,
        ];
    }

    protected $fillable = [
        'live_stream_id',
        'publisher_user_id',
        'name',
        'location',
        'stream_type',
        'stream_url',
        'display_order',
        'is_enabled',
        'connection_status',
        'device_name',
        'battery_level',
        'signal_strength',
        'audio_muted',
        'audio_volume',
        'joined_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'audio_muted' => 'boolean',
            'audio_volume' => 'integer',
            'joined_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_user_id');
    }

    public function isMobilePublisher(): bool
    {
        return $this->publisher_user_id !== null;
    }

    public function isOnline(): bool
    {
        return in_array($this->connection_status, [
            self::STATUS_AVAILABLE,
            self::STATUS_CONNECTING,
            self::STATUS_CONNECTED,
            self::STATUS_READY,
            self::STATUS_LIVE,
        ], true);
    }
}
