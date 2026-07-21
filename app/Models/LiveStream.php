<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveStream extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_LIVE = 'live';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_STOPPED = 'stopped';

    public const STATUS_CANCELLED = 'cancelled';

    public const MODE_INSTANT = 'instant';

    public const MODE_SCHEDULED = 'scheduled';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PARENTS_ONLY = 'parents_only';

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'banner',
        'cms_item_id',
        'mode',
        'event_date',
        'scheduled_start_at',
        'scheduled_end_at',
        'stream_source',
        'enable_countdown',
        'enable_reminder',
        'notify_before_minutes',
        'visibility',
        'auto_start',
        'auto_end',
        'notifications_sent',
        'viewer_count',
        'audio_enabled',
        'status',
        'active_camera_id',
        'layout_mode',
        'active_camera_ids',
        'started_at',
        'paused_at',
        'stopped_at',
        'cancelled_at',
    ];

    /** Picture-in-picture: primary full + secondary mini (2 panes). */
    public const LAYOUT_PIP = 5;

    public static function normalizeLayoutMode(int $mode): int
    {
        if ($mode === self::LAYOUT_PIP) {
            return self::LAYOUT_PIP;
        }

        return max(1, min(4, $mode > 0 ? $mode : 1));
    }

    /** How many camera feeds this layout shows. */
    public static function layoutPaneCount(int $mode): int
    {
        $normalized = self::normalizeLayoutMode($mode);

        return $normalized === self::LAYOUT_PIP ? 2 : $normalized;
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'enable_countdown' => 'boolean',
            'enable_reminder' => 'boolean',
            'notify_before_minutes' => 'array',
            'auto_start' => 'boolean',
            'auto_end' => 'boolean',
            'notifications_sent' => 'array',
            'viewer_count' => 'integer',
            'audio_enabled' => 'boolean',
            'active_camera_ids' => 'array',
            'layout_mode' => 'integer',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'stopped_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cmsItem(): BelongsTo
    {
        return $this->belongsTo(CmsItem::class);
    }

    public function cameras(): HasMany
    {
        return $this->hasMany(LiveStreamCamera::class)->orderBy('display_order');
    }

    public function activeCamera(): BelongsTo
    {
        return $this->belongsTo(LiveStreamCamera::class, 'active_camera_id');
    }

    public function isBroadcasting(): bool
    {
        return in_array($this->status, [self::STATUS_LIVE, self::STATUS_PAUSED], true);
    }

    public function isUpcoming(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->scheduled_start_at
            && $this->scheduled_start_at->isFuture();
    }

    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 'cancelled';
        }
        if ($this->status === self::STATUS_STOPPED) {
            return 'ended';
        }
        if ($this->isUpcoming()) {
            return 'upcoming';
        }
        if ($this->status === self::STATUS_SCHEDULED) {
            return 'scheduled';
        }

        return $this->status;
    }
}
