<?php

namespace App\Services\LiveStream;

use App\Events\LiveStreamUpdated;
use App\Models\CmsItem;
use App\Models\IdCard;
use App\Models\LiveStream;
use App\Models\LiveStreamCamera;
use App\Models\LiveStreamViewerSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SchoolTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LiveStreamService
{
    public function __construct(
        private readonly LiveStreamNotificationService $notifications,
        private readonly LiveStreamUrlValidator $urlValidator,
        private readonly LiveKitService $liveKit,
        private readonly SchoolTimezone $schoolTimezone,
    ) {}

    private function timezone(): string
    {
        return $this->schoolTimezone->get();
    }

    /** @return list<string> */
    public static function adminRoles(): array
    {
        return ['super_admin'];
    }

    /** @return list<string> */
    public static function publisherRoles(): array
    {
        return ['teacher', 'staff'];
    }

    /** @return list<string> */
    public static function staffRoles(): array
    {
        return array_merge(self::adminRoles(), self::publisherRoles());
    }

    /** @return list<string> */
    public static function viewerRoles(): array
    {
        return ['parent', 'student', 'guest'];
    }

    /** Staff + viewers — anyone allowed to watch a live stream when authenticated */
    public static function liveAccessRoles(): array
    {
        return array_merge(self::staffRoles(), self::viewerRoles());
    }

    public function toStaffPayload(LiveStream $stream): array
    {
        $stream->loadMissing(['cameras', 'activeCamera']);

        return [
            'id' => $stream->id,
            'title' => $stream->title,
            'description' => $stream->description,
            'banner' => $stream->banner,
            'cms_item_id' => $stream->cms_item_id,
            'mode' => $stream->mode ?? LiveStream::MODE_INSTANT,
            'event_date' => $stream->event_date?->toDateString(),
            'scheduled_start_at' => $this->formatStaffDateTime($stream->scheduled_start_at),
            'scheduled_end_at' => $this->formatStaffDateTime($stream->scheduled_end_at),
            'stream_source' => $stream->stream_source,
            'enable_countdown' => (bool) $stream->enable_countdown,
            'enable_reminder' => (bool) $stream->enable_reminder,
            'notify_before_minutes' => $stream->notify_before_minutes ?? [60, 30],
            'visibility' => $stream->visibility ?? LiveStream::VISIBILITY_PUBLIC,
            'auto_start' => (bool) $stream->auto_start,
            'auto_end' => (bool) $stream->auto_end,
            'viewer_count' => (int) $stream->viewer_count,
            'audio_enabled' => (bool) ($stream->audio_enabled ?? true),
            'status' => $stream->status,
            'display_status' => $stream->displayStatus(),
            'status_label' => $this->displayStatusLabel($stream),
            'active_camera_id' => $stream->active_camera_id,
            'layout_mode' => $this->resolvedLayoutMode($stream),
            'active_camera_ids' => $this->resolvedActiveCameraIds($stream),
            'started_at' => $stream->started_at?->toIso8601String(),
            'paused_at' => $stream->paused_at?->toIso8601String(),
            'stopped_at' => $stream->stopped_at?->toIso8601String(),
            'cancelled_at' => $stream->cancelled_at?->toIso8601String(),
            'countdown_seconds' => $this->countdownSeconds($stream),
            'cameras' => $stream->cameras->map(fn (LiveStreamCamera $c) => [
                ...$this->toStaffCamera($c),
                'is_active' => in_array((int) $c->id, $this->resolvedActiveCameraIds($stream), true),
                'is_primary' => (int) $stream->active_camera_id === (int) $c->id,
            ])->values()->all(),
            'active_camera' => $stream->activeCamera ? [
                ...$this->toStaffCamera($stream->activeCamera),
                'is_active' => true,
                'is_primary' => true,
            ] : null,
            'active_cameras' => $this->activeCamerasSummary($stream),
        ];
    }

    public function toViewerPayload(LiveStream $stream): array
    {
        $stream->loadMissing(['activeCamera', 'cameras']);

        $active = $stream->activeCamera;
        $activeIds = $this->resolvedActiveCameraIds($stream);
        $watchable = $stream->isBroadcasting()
            && $active
            && $active->is_enabled
            && $activeIds !== []
            && $this->isViewerScheduleOpen($stream);

        return [
            'id' => $stream->id,
            'title' => $stream->title,
            'description' => $stream->description,
            'banner' => $stream->banner,
            'status' => $stream->status,
            'display_status' => $stream->displayStatus(),
            'status_label' => $this->displayStatusLabel($stream),
            'is_watchable' => $watchable,
            'is_upcoming' => $stream->isUpcoming(),
            'is_scheduled' => $stream->status === LiveStream::STATUS_SCHEDULED,
            'enable_countdown' => (bool) $stream->enable_countdown,
            'scheduled_start_at' => $this->formatStaffDateTime($stream->scheduled_start_at),
            'scheduled_end_at' => $this->formatStaffDateTime($stream->scheduled_end_at),
            'countdown_seconds' => $this->countdownSeconds($stream),
            'viewer_count' => (int) $stream->viewer_count,
            'audio_enabled' => (bool) ($stream->audio_enabled ?? true),
            'visibility' => $stream->visibility ?? LiveStream::VISIBILITY_PUBLIC,
            'layout_mode' => $this->resolvedLayoutMode($stream),
            'active_camera_ids' => $activeIds,
            'active_camera' => $active ? [
                'id' => $active->id,
                'name' => $active->name,
                'location' => $active->location,
                'stream_type' => $active->stream_type,
            ] : null,
            'active_cameras' => $this->activeCamerasSummary($stream),
        ];
    }

    public function toWatchPayload(LiveStream $stream): array
    {
        $stream->loadMissing(['activeCamera', 'cameras']);
        $viewer = $this->toViewerPayload($stream);

        if (! $viewer['is_watchable']) {
            return $viewer;
        }

        $playbacks = [];
        foreach ($this->orderedActiveCameras($stream) as $camera) {
            $playbacks[] = [
                ...$this->playbackConfig($stream, $camera),
                'camera_id' => $camera->id,
                'camera_name' => $camera->name,
                'camera_location' => $camera->location,
                'audio_muted' => (bool) $camera->audio_muted,
                'audio_volume' => max(0, min(100, (int) ($camera->audio_volume ?? 100))),
            ];
        }

        if ($playbacks === []) {
            return $viewer;
        }

        // Withhold embeds while paused so clients cannot keep decoding YouTube/LiveKit under a UI overlay.
        if ($stream->status === LiveStream::STATUS_PAUSED) {
            return $viewer;
        }

        return [
            ...$viewer,
            'playback' => $playbacks[0],
            'playbacks' => $playbacks,
        ];
    }

    /** @return array<string, mixed> */
    public function playbackConfig(LiveStream $stream, LiveStreamCamera $camera): array
    {
        $signedPlayback = URL::temporarySignedRoute(
            'live-stream.playback',
            now()->addMinutes(30),
            ['liveStream' => $stream->id, 'camera' => $camera->id],
        );

        return match ($camera->stream_type) {
            LiveStreamCamera::TYPE_YOUTUBE => [
                'mode' => 'youtube',
                'video_id' => $this->extractYoutubeId($camera->stream_url),
            ],
            LiveStreamCamera::TYPE_VIMEO => [
                'mode' => 'vimeo',
                'video_id' => $this->extractVimeoId($camera->stream_url),
            ],
            LiveStreamCamera::TYPE_BUILTIN => [
                'mode' => 'builtin_camera',
                'stream_id' => $stream->id,
                'camera_id' => $camera->id,
                'room_name' => $this->liveKit->roomName($stream),
                'participant_identity' => $this->liveKit->participantIdentity($camera),
            ],
            LiveStreamCamera::TYPE_EMBED, LiveStreamCamera::TYPE_FACEBOOK, LiveStreamCamera::TYPE_RTMP => [
                'mode' => 'signed_redirect',
                'src' => $signedPlayback,
            ],
            default => [
                'mode' => 'signed_redirect',
                'src' => $signedPlayback,
            ],
        };
    }

    public function broadcastUpdate(LiveStream $stream, string $action, ?int $cameraId = null): void
    {
        $stream = $stream->fresh(['activeCamera', 'cameras']);

        try {
            event(new LiveStreamUpdated($stream, $action, $cameraId));
        } catch (\Throwable $e) {
            // Reverb offline should not break start/switch APIs; clients fall back to polling.
            Log::warning('Live stream broadcast failed', [
                'stream_id' => $stream->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parent/public watch page presence. Updates viewer_count and broadcasts when it changes.
     *
     * @return array{viewer_count: int}
     */
    public function heartbeatViewer(LiveStream $stream, string $viewerKey, ?int $userId = null): array
    {
        $viewerKey = Str::lower(trim($viewerKey));
        if ($viewerKey === '' || strlen($viewerKey) < 16 || strlen($viewerKey) > 64) {
            throw ValidationException::withMessages([
                'viewer_key' => 'Invalid viewer key.',
            ]);
        }

        if (! Schema::hasTable('live_stream_viewer_sessions')) {
            return ['viewer_count' => (int) $stream->viewer_count];
        }

        if (! in_array($stream->status, [LiveStream::STATUS_LIVE, LiveStream::STATUS_PAUSED], true)) {
            $this->clearViewerSessions($stream);

            return ['viewer_count' => 0];
        }

        $now = now();

        LiveStreamViewerSession::query()->updateOrCreate(
            [
                'live_stream_id' => $stream->id,
                'viewer_key' => $viewerKey,
            ],
            [
                'user_id' => $userId,
                'last_seen_at' => $now,
            ],
        );

        $count = $this->syncViewerCount($stream);

        return ['viewer_count' => $count];
    }

    public function syncViewerCount(LiveStream $stream, bool $broadcast = true): int
    {
        if (! Schema::hasTable('live_stream_viewer_sessions')) {
            return (int) $stream->viewer_count;
        }

        $cutoff = now()->subSeconds(LiveStreamViewerSession::TTL_SECONDS);

        LiveStreamViewerSession::query()
            ->where('live_stream_id', $stream->id)
            ->where('last_seen_at', '<', $cutoff)
            ->delete();

        $count = LiveStreamViewerSession::query()
            ->where('live_stream_id', $stream->id)
            ->where('last_seen_at', '>=', $cutoff)
            ->count();

        $previous = (int) $stream->viewer_count;
        if ($previous !== $count) {
            $stream->update(['viewer_count' => $count]);
            if ($broadcast) {
                $this->broadcastUpdate($stream->fresh(['activeCamera', 'cameras']), 'viewer_count');
            }
        }

        return $count;
    }

    public function clearViewerSessions(LiveStream $stream): void
    {
        if (Schema::hasTable('live_stream_viewer_sessions')) {
            LiveStreamViewerSession::query()->where('live_stream_id', $stream->id)->delete();
        }

        if ((int) $stream->viewer_count !== 0) {
            $stream->update(['viewer_count' => 0]);
        }
    }

    public function setActiveCamera(LiveStream $stream, int $cameraId): LiveStream
    {
        $camera = $stream->cameras()->whereKey($cameraId)->first();
        if (! $camera || ! $camera->is_enabled) {
            throw ValidationException::withMessages(['camera_id' => 'Camera not found or disabled.']);
        }

        $layout = $this->resolvedLayoutMode($stream);
        $ids = $this->resolvedActiveCameraIds($stream);
        $ids = array_values(array_filter($ids, fn (int $id) => $id !== (int) $camera->id));
        array_unshift($ids, (int) $camera->id);
        $ids = array_slice($ids, 0, LiveStream::layoutPaneCount($layout));

        $stream->update([
            'active_camera_id' => $camera->id,
            'active_camera_ids' => $ids,
        ]);
        $this->syncMobileCameraStatuses($stream->fresh(['cameras', 'activeCamera']));
        $this->broadcastUpdate($stream->fresh(['activeCamera', 'cameras']), 'camera_switched');

        return $stream->fresh(['activeCamera', 'cameras']);
    }

    public function setLayoutMode(LiveStream $stream, int $layoutMode): LiveStream
    {
        $enabledCount = $stream->cameras()->where('is_enabled', true)->count();
        $wantPip = $layoutMode === LiveStream::LAYOUT_PIP;

        if ($wantPip) {
            if ($enabledCount < 1) {
                throw ValidationException::withMessages([
                    'layout_mode' => 'Enable at least one camera before selecting PiP.',
                ]);
            }
            $mode = LiveStream::LAYOUT_PIP;
            $paneCount = LiveStream::layoutPaneCount($mode);
        } else {
            // Keep the admin-selected grid (1–4) even if fewer cameras are active yet.
            $mode = max(1, min(4, $layoutMode));
            $paneCount = $mode;
        }

        $ids = $this->resolvedActiveCameraIds($stream);
        if ($ids === [] && $stream->active_camera_id) {
            $ids = [(int) $stream->active_camera_id];
        }
        $ids = array_slice($ids, 0, $paneCount);

        $stream->update([
            'layout_mode' => $mode,
            'active_camera_ids' => $ids,
            'active_camera_id' => $ids[0] ?? $stream->active_camera_id,
        ]);

        $this->syncMobileCameraStatuses($stream->fresh(['cameras', 'activeCamera']));
        $this->broadcastUpdate($stream->fresh(['activeCamera', 'cameras']), 'layout_updated');

        return $stream->fresh(['activeCamera', 'cameras']);
    }

    /** @param list<int> $cameraIds */
    public function setActiveCameras(LiveStream $stream, array $cameraIds): LiveStream
    {
        $enabledCount = $stream->cameras()->where('is_enabled', true)->count();
        $currentMode = LiveStream::normalizeLayoutMode((int) ($stream->layout_mode ?? 1));
        // Layout mode is chosen by admin (1–4 / PiP). Activating cameras only fills slots.
        $paneCap = LiveStream::layoutPaneCount($currentMode);
        $max = min($paneCap, max(1, $enabledCount > 0 ? $enabledCount : $paneCap));

        $unique = [];
        foreach ($cameraIds as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $unique, true)) {
                $unique[] = $id;
            }
        }

        if ($unique === []) {
            throw ValidationException::withMessages(['camera_ids' => 'Select at least one camera.']);
        }

        if (count($unique) > $max) {
            $isPip = $currentMode === LiveStream::LAYOUT_PIP;
            throw ValidationException::withMessages([
                'camera_ids' => $isPip
                    ? 'PiP layout uses at most 2 cameras (main + mini).'
                    : "This layout uses at most {$max} camera(s).",
            ]);
        }

        $unique = array_slice($unique, 0, $max);
        $cameras = $stream->cameras()->whereIn('id', $unique)->where('is_enabled', true)->get()->keyBy('id');

        $ordered = [];
        foreach ($unique as $id) {
            if (! $cameras->has($id)) {
                throw ValidationException::withMessages([
                    'camera_ids' => "Camera {$id} was not found or is disabled.",
                ]);
            }
            $ordered[] = $id;
        }

        $stream->update([
            'layout_mode' => $currentMode,
            'active_camera_ids' => $ordered,
            'active_camera_id' => $ordered[0],
        ]);

        $this->syncMobileCameraStatuses($stream->fresh(['cameras', 'activeCamera']));
        $this->broadcastUpdate($stream->fresh(['activeCamera', 'cameras']), 'cameras_activated');

        return $stream->fresh(['activeCamera', 'cameras']);
    }

    public function joinCamera(LiveStream $stream, User $user, array $data): LiveStreamCamera
    {
        if (! $this->canPublisherJoin($stream)) {
            throw ValidationException::withMessages([
                'stream' => 'This event is not open for camera connections yet. Ask admin to link and schedule the CMS event.',
            ]);
        }

        $this->disconnectUserCameras($user, exceptStreamId: $stream->id);

        $existing = $stream->cameras()
            ->where('publisher_user_id', $user->id)
            ->first();

        $name = trim((string) ($data['name'] ?? '')) ?: $user->name.' Camera';
        $location = trim((string) ($data['location'] ?? '')) ?: null;
        $deviceName = trim((string) ($data['device_name'] ?? '')) ?: null;

        if ($existing) {
            $existing->update([
                'name' => $name,
                'location' => $location,
                'device_name' => $deviceName,
                'connection_status' => LiveStreamCamera::STATUS_CONNECTING,
                'is_enabled' => true,
                'joined_at' => $existing->joined_at ?? now(),
                'last_seen_at' => now(),
            ]);

            $camera = $existing->fresh();
        } else {
            $order = (int) $stream->cameras()->max('display_order') + 1;
            $camera = $stream->cameras()->create([
                'publisher_user_id' => $user->id,
                'name' => $name,
                'location' => $location,
                'stream_type' => LiveStreamCamera::TYPE_BUILTIN,
                'stream_url' => 'builtin://mobile',
                'display_order' => $order,
                'is_enabled' => true,
                'connection_status' => LiveStreamCamera::STATUS_CONNECTING,
                'device_name' => $deviceName,
                'joined_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        $this->broadcastUpdate($stream->fresh(['activeCamera', 'cameras']), 'camera_session_updated', $camera->id);

        return $camera->load('publisher.roles');
    }

    public function updateCameraSession(LiveStream $stream, LiveStreamCamera $camera, User $user, array $data): LiveStreamCamera
    {
        $this->assertPublisherOwnsCamera($camera, $user);

        if ((int) $camera->live_stream_id !== (int) $stream->id) {
            throw ValidationException::withMessages(['camera' => 'Camera not found on this stream.']);
        }

        $status = $data['connection_status'] ?? $camera->connection_status;
        if (! in_array($status, LiveStreamCamera::connectionStatuses(), true)) {
            throw ValidationException::withMessages(['connection_status' => 'Invalid connection status.']);
        }

        $camera->update([
            'connection_status' => $status,
            'device_name' => $data['device_name'] ?? $camera->device_name,
            'battery_level' => array_key_exists('battery_level', $data) ? $data['battery_level'] : $camera->battery_level,
            'signal_strength' => array_key_exists('signal_strength', $data) ? $data['signal_strength'] : $camera->signal_strength,
            'last_seen_at' => now(),
        ]);

        if ($status === LiveStreamCamera::STATUS_LIVE && (int) $stream->active_camera_id !== (int) $camera->id) {
            $camera->update(['connection_status' => LiveStreamCamera::STATUS_READY]);
        }

        $this->broadcastUpdate($stream->fresh(['activeCamera', 'cameras']), 'camera_session_updated', $camera->id);

        return $camera->fresh(['publisher.roles']);
    }

    public function disconnectCamera(LiveStream $stream, LiveStreamCamera $camera, User $user, bool $adminForce = false): LiveStreamCamera
    {
        if ((int) $camera->live_stream_id !== (int) $stream->id) {
            throw ValidationException::withMessages(['camera' => 'Camera not found on this stream.']);
        }

        if (! $adminForce) {
            $this->assertPublisherOwnsCamera($camera, $user);
        }

        $camera->update([
            'connection_status' => LiveStreamCamera::STATUS_DISCONNECTED,
            'last_seen_at' => now(),
        ]);

        $nextPrimary = (int) $stream->active_camera_id === (int) $camera->id
            ? ($stream->cameras()
                ->where('id', '!=', $camera->id)
                ->where('is_enabled', true)
                ->whereIn('connection_status', [
                    LiveStreamCamera::STATUS_READY,
                    LiveStreamCamera::STATUS_CONNECTED,
                    LiveStreamCamera::STATUS_LIVE,
                ])
                ->orderBy('display_order')
                ->first()?->id)
            : $stream->active_camera_id;

        $this->setActiveCamerasAfterRemoval($stream, (int) $camera->id, $nextPrimary ? (int) $nextPrimary : null);

        $this->broadcastUpdate($stream->fresh(['activeCamera', 'cameras']), 'camera_disconnected', $camera->id);

        return $camera->fresh(['publisher.roles']);
    }

    public function setCameraAudioMuted(LiveStream $stream, LiveStreamCamera $camera, bool $muted): LiveStreamCamera
    {
        if ((int) $camera->live_stream_id !== (int) $stream->id) {
            throw ValidationException::withMessages(['camera' => 'Camera not found on this stream.']);
        }

        $payload = ['audio_muted' => $muted];
        // Unmute with volume 0 would stay silent for parents — restore a usable level.
        if (! $muted && Schema::hasColumn('live_stream_cameras', 'audio_volume')) {
            $currentVolume = (int) ($camera->audio_volume ?? 100);
            if ($currentVolume <= 0) {
                $payload['audio_volume'] = 100;
            }
        }

        $camera->update($payload);
        $this->broadcastUpdate($stream->fresh(['activeCamera', 'cameras']), 'camera_audio_updated', $camera->id);

        return $camera->fresh(['publisher.roles']);
    }

    public function setCameraAudioVolume(LiveStream $stream, LiveStreamCamera $camera, int $volume): LiveStreamCamera
    {
        if ((int) $camera->live_stream_id !== (int) $stream->id) {
            throw ValidationException::withMessages(['camera' => 'Camera not found on this stream.']);
        }

        $volume = max(0, min(100, $volume));
        $payload = [
            // Moving the slider above 0 unmutes; 0 keeps mute semantics for parents.
            'audio_muted' => $volume === 0,
        ];
        // Avoid 500 if production has not run the audio_volume migration yet.
        if (Schema::hasColumn('live_stream_cameras', 'audio_volume')) {
            $payload['audio_volume'] = $volume;
        }

        $camera->update($payload);
        $this->broadcastUpdate($stream->fresh(['activeCamera', 'cameras']), 'camera_audio_updated', $camera->id);

        return $camera->fresh(['publisher.roles']);
    }

    /** @return list<array<string, mixed>> */
    public function publisherEventsForUser(): array
    {
        return LiveStream::query()
            ->where(function ($query) {
                $query->whereIn('status', [
                    LiveStream::STATUS_LIVE,
                    LiveStream::STATUS_PAUSED,
                    LiveStream::STATUS_SCHEDULED,
                ])->orWhere(function ($query) {
                    $query->where('status', LiveStream::STATUS_DRAFT)
                        ->whereNotNull('cms_item_id');
                });
            })
            ->where('status', '!=', LiveStream::STATUS_CANCELLED)
            ->orderByRaw("CASE WHEN status IN ('live', 'paused') THEN 0 WHEN status = 'scheduled' THEN 1 ELSE 2 END")
            ->orderBy('scheduled_start_at')
            ->get()
            ->map(fn (LiveStream $stream) => [
                'id' => $stream->id,
                'title' => $stream->title,
                'description' => $stream->description,
                'status' => $stream->status,
                'display_status' => $stream->displayStatus(),
                'status_label' => $this->displayStatusLabel($stream),
                'scheduled_start_at' => $this->formatStaffDateTime($stream->scheduled_start_at),
                'can_join' => $this->canPublisherJoin($stream),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function toPublisherJoinPayload(LiveStream $stream, LiveStreamCamera $camera): array
    {
        return [
            'stream' => [
                'id' => $stream->id,
                'title' => $stream->title,
                'status' => $stream->status,
                'display_status' => $stream->displayStatus(),
                'status_label' => $this->displayStatusLabel($stream),
            ],
            'camera' => $this->toPublisherCamera($camera),
        ];
    }

    public function reorderCameras(LiveStream $stream, array $orderedIds): LiveStream
    {
        foreach (array_values($orderedIds) as $index => $id) {
            $stream->cameras()->whereKey($id)->update(['display_order' => $index]);
        }

        $this->broadcastUpdate($stream->fresh(), 'cameras_reordered');

        return $stream->fresh(['activeCamera', 'cameras']);
    }

    public function scheduleStream(LiveStream $stream, array $data): LiveStream
    {
        $data = $this->normalizeScheduleDates($data);

        $stream->update([
            ...$data,
            'mode' => LiveStream::MODE_SCHEDULED,
            'status' => LiveStream::STATUS_SCHEDULED,
            'notify_before_minutes' => $data['notify_before_minutes'] ?? [60, 30],
            'auto_start' => array_key_exists('auto_start', $data) ? (bool) $data['auto_start'] : true,
            'auto_end' => array_key_exists('auto_end', $data) ? (bool) $data['auto_end'] : ($stream->auto_end ?? true),
        ]);

        if ($stream->enable_reminder && ! $this->notifications->wasSent($stream, 'scheduled')) {
            $this->notifications->notifyParents(
                $stream,
                LiveStreamNotificationService::TYPE_SCHEDULED,
                'Live event scheduled',
                "{$stream->title} is scheduled for ".$stream->scheduled_start_at?->format('M j, g:i A').'.',
            );
            $this->notifications->markSent($stream, 'scheduled');
        }

        $stream = $stream->fresh(['activeCamera', 'cameras']);
        $this->syncStreamToCms($stream);
        $this->broadcastUpdate($stream, 'scheduled');

        return $stream;
    }

    public function updateStream(LiveStream $stream, array $data): LiveStream
    {
        if (in_array($stream->status, [LiveStream::STATUS_LIVE, LiveStream::STATUS_PAUSED], true)) {
            $stream->update(array_intersect_key($data, array_flip(['title', 'description', 'banner', 'audio_enabled'])));
        } else {
            if (isset($data['notify_before_minutes']) && $data['notify_before_minutes'] === null) {
                unset($data['notify_before_minutes']);
            }
            $data = $this->normalizeScheduleDates($data);

            // Allow admin to move draft/stopped/cancelled ↔ scheduled without going live.
            if (array_key_exists('status', $data)) {
                $next = $data['status'];
                $editable = in_array($stream->status, [
                    LiveStream::STATUS_DRAFT,
                    LiveStream::STATUS_SCHEDULED,
                    LiveStream::STATUS_STOPPED,
                    LiveStream::STATUS_CANCELLED,
                ], true);

                if ($editable && $next === LiveStream::STATUS_DRAFT) {
                    $data['status'] = LiveStream::STATUS_DRAFT;
                    $data['mode'] = LiveStream::MODE_INSTANT;
                    $data['cancelled_at'] = null;
                } elseif ($editable && $next === LiveStream::STATUS_SCHEDULED) {
                    // Scheduling with countdown requires the dedicated schedule endpoint.
                    unset($data['status']);
                } else {
                    unset($data['status']);
                }
            }

            $stream->update($data);
        }

        $stream = $stream->fresh(['activeCamera', 'cameras']);
        $this->syncStreamToCms($stream);
        $this->broadcastUpdate($stream, 'updated');

        return $stream;
    }

    public function cancelStream(LiveStream $stream): LiveStream
    {
        if (in_array($stream->status, [LiveStream::STATUS_LIVE, LiveStream::STATUS_PAUSED], true)) {
            throw ValidationException::withMessages(['status' => 'Stop the live stream before cancelling.']);
        }

        $stream->update([
            'status' => LiveStream::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->broadcastUpdate($stream->fresh(), 'cancelled');

        return $stream->fresh(['activeCamera', 'cameras']);
    }

    public function findActiveForViewer(bool $publicOnly = false): ?LiveStream
    {
        $q = LiveStream::query()
            ->whereIn('status', [LiveStream::STATUS_LIVE, LiveStream::STATUS_PAUSED])
            ->with('activeCamera')
            ->latest('started_at');

        if ($publicOnly) {
            $q->where('visibility', LiveStream::VISIBILITY_PUBLIC);
        }

        return $q->first();
    }

    /** @return \Illuminate\Support\Collection<int, LiveStream> */
    public function findUpcomingForViewer(bool $publicOnly = false)
    {
        $q = LiveStream::query()
            ->where('status', LiveStream::STATUS_SCHEDULED)
            ->orderBy('scheduled_start_at');

        if ($publicOnly) {
            $q->where('visibility', LiveStream::VISIBILITY_PUBLIC);
        }

        return $q->get();
    }

    public function findScheduledForViewer(bool $publicOnly = false): ?LiveStream
    {
        return $this->findUpcomingForViewer($publicOnly)->first();
    }

    public function processAutoStartEnd(): void
    {
        if (! Cache::add('live_streams:auto_process_lock', true, 30)) {
            return;
        }

        try {
            $now = now($this->timezone());

            LiveStream::query()
                ->where('status', LiveStream::STATUS_SCHEDULED)
                ->where('auto_start', true)
                ->whereNotNull('scheduled_start_at')
                ->where('scheduled_start_at', '<=', $now)
                ->orderBy('scheduled_start_at')
                ->each(function (LiveStream $stream) {
                    try {
                        $fresh = $stream->fresh(['cameras', 'activeCamera']);
                        if (! $fresh || $fresh->status !== LiveStream::STATUS_SCHEDULED) {
                            return;
                        }

                        $this->startLive($fresh);

                        if (! $this->notifications->wasSent($fresh, 'started')) {
                            $this->notifications->notifyParents(
                                $fresh->fresh(),
                                LiveStreamNotificationService::TYPE_STARTED,
                                'Live started now!',
                                "{$fresh->title} is live. Tap Watch Live in the parent portal.",
                            );
                            $this->notifications->markSent($fresh, 'started');
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Live stream auto-start failed', [
                            'stream_id' => $stream->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });

            LiveStream::query()
                ->whereIn('status', [LiveStream::STATUS_LIVE, LiveStream::STATUS_PAUSED])
                ->where('auto_end', true)
                ->whereNotNull('scheduled_end_at')
                ->where('scheduled_end_at', '<=', $now)
                ->each(function (LiveStream $stream) {
                    try {
                        $this->stopLive($stream);
                        if (! $this->notifications->wasSent($stream, 'ended')) {
                            $this->notifications->notifyParents(
                                $stream->fresh(),
                                LiveStreamNotificationService::TYPE_ENDED,
                                'Live event ended',
                                "{$stream->title} has ended. Thank you for watching!",
                            );
                            $this->notifications->markSent($stream, 'ended');
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Live stream auto-end failed', [
                            'stream_id' => $stream->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
        } finally {
            Cache::forget('live_streams:auto_process_lock');
        }
    }

    public function processReminderNotifications(): void
    {
        $now = now();

        LiveStream::query()
            ->where('status', LiveStream::STATUS_SCHEDULED)
            ->where('enable_reminder', true)
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '>', $now)
            ->each(function (LiveStream $stream) use ($now) {
                $minutesUntil = (int) $now->diffInMinutes($stream->scheduled_start_at, false);
                foreach ($stream->notify_before_minutes ?? [60, 30] as $mins) {
                    $key = "reminder_{$mins}";
                    if ($minutesUntil <= $mins && $minutesUntil > $mins - 2 && ! $this->notifications->wasSent($stream, $key)) {
                        $this->notifications->notifyParents(
                            $stream,
                            LiveStreamNotificationService::TYPE_REMINDER,
                            "Live starts in {$mins} minutes",
                            "{$stream->title} goes live at ".$stream->scheduled_start_at->format('g:i A').'. Open the parent portal to watch.',
                        );
                        $this->notifications->markSent($stream, $key);
                    }
                }
            });
    }

    public function startLive(LiveStream $stream, bool $notify = false): LiveStream
    {
        $stream->load('cameras');
        $enabledCameras = $stream->cameras->where('is_enabled', true);

        $activeId = $stream->active_camera_id;
        if (! $activeId || ! $enabledCameras->contains('id', (int) $activeId)) {
            $activeId = $this->resolveDefaultActiveCamera($enabledCameras);
        }

        if (! $activeId) {
            throw ValidationException::withMessages([
                'cameras' => 'No cameras available. Ask a teacher or staff member to connect from Join Live on their phone, or add an external stream camera first.',
            ]);
        }

        $this->stopOtherLiveStreams($stream->id);

        $layout = $this->resolvedLayoutMode($stream);
        $ids = $this->resolvedActiveCameraIds($stream);
        $ids = array_values(array_filter($ids, fn (int $id) => $id !== (int) $activeId));
        array_unshift($ids, (int) $activeId);
        $ids = array_slice($ids, 0, LiveStream::layoutPaneCount($layout));

        $stream->update([
            'status' => LiveStream::STATUS_LIVE,
            'active_camera_id' => $activeId,
            'active_camera_ids' => $ids,
            'started_at' => now(),
            'paused_at' => null,
            'stopped_at' => null,
            'viewer_count' => 0,
        ]);

        $this->clearViewerSessions($stream->fresh());

        $fresh = $stream->fresh(['activeCamera', 'cameras']);
        $this->syncMobileCameraStatuses($fresh);
        $this->broadcastUpdate($fresh, 'started');

        if ($notify && ! $this->notifications->wasSent($stream, 'started')) {
            $this->notifications->notifyParents(
                $stream->fresh(),
                LiveStreamNotificationService::TYPE_STARTED,
                'Live started now!',
                "{$stream->title} is live. Tap Watch Live to join.",
            );
            $this->notifications->markSent($stream, 'started');
        }

        return $stream->fresh(['activeCamera', 'cameras']);
    }

    /** @param \Illuminate\Support\Collection<int, LiveStreamCamera> $enabledCameras */
    private function resolveDefaultActiveCamera($enabledCameras): ?int
    {
        if ($enabledCameras->isEmpty()) {
            return null;
        }

        $publishableStatuses = [
            LiveStreamCamera::STATUS_READY,
            LiveStreamCamera::STATUS_CONNECTED,
            LiveStreamCamera::STATUS_LIVE,
            LiveStreamCamera::STATUS_AVAILABLE,
        ];

        $mobileReady = $enabledCameras
            ->filter(fn (LiveStreamCamera $c) => $c->isMobilePublisher()
                && in_array($c->connection_status, $publishableStatuses, true))
            ->sortBy('display_order')
            ->first();

        if ($mobileReady) {
            return $mobileReady->id;
        }

        $anyMobile = $enabledCameras
            ->filter(fn (LiveStreamCamera $c) => $c->isMobilePublisher() && $c->isOnline())
            ->sortBy('display_order')
            ->first();

        if ($anyMobile) {
            return $anyMobile->id;
        }

        return $enabledCameras->sortBy('display_order')->first()?->id;
    }

    public function pauseLive(LiveStream $stream): LiveStream
    {
        if ($stream->status !== LiveStream::STATUS_LIVE) {
            throw ValidationException::withMessages(['status' => 'Only a live stream can be paused.']);
        }

        $stream->update([
            'status' => LiveStream::STATUS_PAUSED,
            'paused_at' => now(),
        ]);

        $this->broadcastUpdate($stream->fresh(), 'paused');

        return $stream->fresh(['activeCamera', 'cameras']);
    }

    public function resumeLive(LiveStream $stream): LiveStream
    {
        if ($stream->status !== LiveStream::STATUS_PAUSED) {
            throw ValidationException::withMessages(['status' => 'Only a paused stream can be resumed.']);
        }

        $stream->update([
            'status' => LiveStream::STATUS_LIVE,
            'paused_at' => null,
        ]);

        $this->broadcastUpdate($stream->fresh(), 'resumed');

        return $stream->fresh(['activeCamera', 'cameras']);
    }

    public function stopLive(LiveStream $stream): LiveStream
    {
        $stream->update([
            'status' => LiveStream::STATUS_STOPPED,
            'stopped_at' => now(),
            'viewer_count' => 0,
        ]);

        $this->clearViewerSessions($stream->fresh());

        $stream->cameras()
            ->whereNotNull('publisher_user_id')
            ->update(['connection_status' => LiveStreamCamera::STATUS_OFFLINE]);

        $this->broadcastUpdate($stream->fresh(), 'stopped');

        if (! $this->notifications->wasSent($stream, 'ended')) {
            $this->notifications->notifyParents(
                $stream->fresh(),
                LiveStreamNotificationService::TYPE_ENDED,
                'Live event ended',
                "{$stream->title} has ended.",
            );
            $this->notifications->markSent($stream, 'ended');
        }

        return $stream->fresh(['activeCamera', 'cameras']);
    }

    public function deletePermanently(LiveStream $stream, bool $deleteCms = true): void
    {
        if ($stream->isBroadcasting()) {
            $this->stopLive($stream->fresh());
        }

        $cmsItemId = $stream->cms_item_id;

        $stream->update(['active_camera_id' => null]);
        $stream->cameras()->delete();
        $stream->delete();

        if ($deleteCms && $cmsItemId) {
            $cmsItem = CmsItem::query()->find($cmsItemId);
            if ($cmsItem) {
                app(\App\Services\Cms\CmsDeletionService::class)->deletePermanently($cmsItem, deleteLinkedStream: false);
            }
        }
    }

    private function stopOtherLiveStreams(int $exceptStreamId): void
    {
        LiveStream::query()
            ->whereIn('status', [LiveStream::STATUS_LIVE, LiveStream::STATUS_PAUSED])
            ->whereKeyNot($exceptStreamId)
            ->get()
            ->each(fn (LiveStream $other) => $this->stopLive($other));
    }

    public function resolvePlaybackCamera(LiveStream $stream, int $cameraId): ?LiveStreamCamera
    {
        $activeIds = $this->resolvedActiveCameraIds($stream);
        if (! in_array($cameraId, $activeIds, true)) {
            return null;
        }

        if (! $stream->isBroadcasting()) {
            return null;
        }

        return $stream->cameras()->whereKey($cameraId)->where('is_enabled', true)->first();
    }

    /** @return list<int> */
    public function resolvedActiveCameraIds(LiveStream $stream): array
    {
        $ids = $stream->active_camera_ids;
        if (is_array($ids) && $ids !== []) {
            return array_values(array_map('intval', $ids));
        }

        return $stream->active_camera_id ? [(int) $stream->active_camera_id] : [];
    }

    public function resolvedLayoutMode(LiveStream $stream): int
    {
        return LiveStream::normalizeLayoutMode((int) ($stream->layout_mode ?? 1));
    }

    /** @return list<LiveStreamCamera> */
    private function orderedActiveCameras(LiveStream $stream): array
    {
        $stream->loadMissing('cameras');
        $byId = $stream->cameras->keyBy('id');
        $ordered = [];
        foreach ($this->resolvedActiveCameraIds($stream) as $id) {
            $camera = $byId->get($id);
            if ($camera && $camera->is_enabled) {
                $ordered[] = $camera;
            }
        }

        return $ordered;
    }

    /** @return list<array<string, mixed>> */
    private function activeCamerasSummary(LiveStream $stream): array
    {
        return array_map(fn (LiveStreamCamera $camera) => [
            'id' => $camera->id,
            'name' => $camera->name,
            'location' => $camera->location,
            'stream_type' => $camera->stream_type,
            'audio_muted' => (bool) $camera->audio_muted,
            'audio_volume' => max(0, min(100, (int) ($camera->audio_volume ?? 100))),
        ], $this->orderedActiveCameras($stream));
    }

    public function setActiveCamerasAfterRemoval(LiveStream $stream, int $removedId, ?int $preferredPrimary): void
    {
        $ids = array_values(array_filter(
            $this->resolvedActiveCameraIds($stream),
            fn (int $id) => $id !== $removedId,
        ));

        if ($preferredPrimary && ! in_array((int) $preferredPrimary, $ids, true)) {
            array_unshift($ids, (int) $preferredPrimary);
        }

        $layout = $this->resolvedLayoutMode($stream);
        $ids = array_slice($ids, 0, LiveStream::layoutPaneCount($layout));

        $stream->update([
            'active_camera_ids' => $ids,
            'active_camera_id' => $ids[0] ?? null,
        ]);
    }

    public function createStream(array $data): LiveStream
    {
        $tenant = Tenant::query()->first();

        return LiveStream::create([
            ...$data,
            'tenant_id' => $tenant?->id,
            'status' => $data['status'] ?? LiveStream::STATUS_DRAFT,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function cmsEventsForLiveStream(): array
    {
        $linked = LiveStream::query()
            ->whereNotNull('cms_item_id')
            ->pluck('id', 'cms_item_id');

        return CmsItem::query()
            ->where('type', 'event')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (CmsItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'summary' => $item->summary,
                'image' => $item->image,
                'meta' => $item->meta ?? [],
                'status' => $item->status,
                'slug' => $item->slug,
                'live_stream_id' => $linked[$item->id] ?? null,
            ])
            ->values()
            ->all();
    }

    public function linkFromCms(CmsItem $cmsItem): LiveStream
    {
        if ($cmsItem->type !== 'event') {
            throw ValidationException::withMessages([
                'cms_item' => 'Only website CMS events can be linked for live streaming.',
            ]);
        }

        $existing = LiveStream::query()
            ->where('cms_item_id', $cmsItem->id)
            ->with(['cameras', 'activeCamera'])
            ->first();

        $payload = $this->streamPayloadFromCms($cmsItem, includeIdentity: ! $existing);

        if ($existing) {
            if (in_array($existing->status, [
                LiveStream::STATUS_DRAFT,
                LiveStream::STATUS_STOPPED,
                LiveStream::STATUS_CANCELLED,
            ], true)) {
                $existing->update($payload);
            }

            return $existing->fresh(['cameras', 'activeCamera']);
        }

        return $this->createStream($payload)->load(['cameras', 'activeCamera']);
    }

    /** @return array<string, mixed> */
    private function streamPayloadFromCms(CmsItem $cmsItem, bool $includeIdentity = true): array
    {
        $meta = is_array($cmsItem->meta) ? $cmsItem->meta : [];

        $payload = [
            'title' => $cmsItem->title,
            'description' => $cmsItem->summary ?: $cmsItem->body,
            'banner' => $cmsItem->image,
            'event_date' => $meta['date'] ?? null,
            'enable_countdown' => true,
            'enable_reminder' => true,
            'notify_before_minutes' => [60, 30],
            'visibility' => LiveStream::VISIBILITY_PUBLIC,
            'auto_start' => true,
            'auto_end' => true,
        ];

        if ($includeIdentity) {
            $payload['cms_item_id'] = $cmsItem->id;
        }

        if (! empty($meta['date'])) {
            $time = (string) ($meta['time'] ?? '09:00');
            $time = strlen($time) === 5 ? $time.':00' : $time;

            try {
                $payload['scheduled_start_at'] = Carbon::parse($meta['date'].' '.$time, $this->timezone());
                $payload['status'] = LiveStream::STATUS_SCHEDULED;
                $payload['mode'] = LiveStream::MODE_SCHEDULED;
            } catch (\Throwable) {
                // Ignore invalid CMS date/time values.
            }
        }

        return $payload;
    }

    private function syncStreamToCms(LiveStream $stream): void
    {
        if (! $stream->cms_item_id) {
            return;
        }

        $cmsItem = CmsItem::query()->find($stream->cms_item_id);
        if (! $cmsItem || $cmsItem->type !== 'event') {
            return;
        }

        $meta = is_array($cmsItem->meta) ? $cmsItem->meta : [];

        if ($stream->event_date) {
            $meta['date'] = $stream->event_date->toDateString();
        }

        if ($stream->scheduled_start_at) {
            $start = $stream->scheduled_start_at->copy()->timezone($this->timezone());
            $meta['date'] = $start->toDateString();
            $meta['time'] = $start->format('H:i');
        }

        $cmsItem->update([
            'title' => $stream->title,
            'summary' => $stream->description,
            'image' => $stream->banner,
            'meta' => $meta,
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalizeScheduleDates(array $data): array
    {
        foreach (['scheduled_start_at', 'scheduled_end_at'] as $field) {
            if (empty($data[$field]) || ! is_string($data[$field])) {
                continue;
            }

            $parsed = $this->parseScheduleInput($data[$field]);
            if ($parsed) {
                $data[$field] = $parsed;
            }
        }

        return $data;
    }

    private function parseScheduleInput(string $value): ?Carbon
    {
        $tz = $this->timezone();

        // datetime-local from admin UI — wall clock in school timezone
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d\TH:i', $value, $tz);
        }

        return Carbon::parse($value, $tz);
    }

    private function formatStaffDateTime(?Carbon $dateTime): ?string
    {
        if (! $dateTime) {
            return null;
        }

        return $dateTime->copy()->timezone($this->timezone())->format('Y-m-d\TH:i');
    }

    private function toStaffCamera(LiveStreamCamera $camera): array
    {
        $camera->loadMissing(['publisher.roles']);

        $publisher = $camera->publisher;
        $photoUrl = null;
        $roleLabel = null;

        if ($publisher) {
            $idCard = IdCard::query()
                ->where('user_id', $publisher->id)
                ->whereIn('card_type', ['teacher', 'staff'])
                ->where('status', 'active')
                ->first();

            $photoUrl = $this->photoUrl($idCard?->photo_path);
            $roleLabel = $publisher->roles->first()?->label ?? $publisher->roles->first()?->name;
        }

        return [
            'id' => $camera->id,
            'name' => $camera->name,
            'location' => $camera->location,
            'stream_type' => $camera->stream_type,
            'stream_url' => $camera->stream_url,
            'display_order' => $camera->display_order,
            'is_enabled' => $camera->is_enabled,
            'publisher_user_id' => $camera->publisher_user_id,
            'publisher_name' => $publisher?->name,
            'publisher_role' => $roleLabel,
            'publisher_photo_url' => $photoUrl,
            'connection_status' => $camera->connection_status ?? LiveStreamCamera::STATUS_OFFLINE,
            'connection_status_label' => $this->connectionStatusLabel($camera->connection_status ?? LiveStreamCamera::STATUS_OFFLINE),
            'device_name' => $camera->device_name,
            'battery_level' => $camera->battery_level,
            'signal_strength' => $camera->signal_strength,
            'audio_muted' => (bool) $camera->audio_muted,
            'audio_volume' => max(0, min(100, (int) ($camera->audio_volume ?? 100))),
            'joined_at' => $camera->joined_at?->toIso8601String(),
            'last_seen_at' => $camera->last_seen_at?->toIso8601String(),
            'is_mobile_publisher' => $camera->isMobilePublisher(),
        ];
    }

    /** @return array<string, mixed> */
    private function toPublisherCamera(LiveStreamCamera $camera): array
    {
        return [
            'id' => $camera->id,
            'name' => $camera->name,
            'location' => $camera->location,
            'stream_type' => $camera->stream_type,
            'connection_status' => $camera->connection_status ?? LiveStreamCamera::STATUS_OFFLINE,
            'connection_status_label' => $this->connectionStatusLabel($camera->connection_status ?? LiveStreamCamera::STATUS_OFFLINE),
            'device_name' => $camera->device_name,
            'audio_muted' => (bool) $camera->audio_muted,
            'audio_volume' => max(0, min(100, (int) ($camera->audio_volume ?? 100))),
            'joined_at' => $camera->joined_at?->toIso8601String(),
        ];
    }

    private function connectionStatusLabel(string $status): string
    {
        return match ($status) {
            LiveStreamCamera::STATUS_AVAILABLE => 'Available',
            LiveStreamCamera::STATUS_CONNECTING => 'Connecting',
            LiveStreamCamera::STATUS_CONNECTED => 'Connected',
            LiveStreamCamera::STATUS_READY => 'Ready',
            LiveStreamCamera::STATUS_LIVE => 'Live',
            LiveStreamCamera::STATUS_DISCONNECTED => 'Disconnected',
            default => 'Offline',
        };
    }

    private function photoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    private function canPublisherJoin(LiveStream $stream): bool
    {
        if ($stream->status === LiveStream::STATUS_CANCELLED) {
            return false;
        }

        if (in_array($stream->status, [
            LiveStream::STATUS_LIVE,
            LiveStream::STATUS_PAUSED,
            LiveStream::STATUS_SCHEDULED,
        ], true)) {
            return true;
        }

        return $stream->status === LiveStream::STATUS_DRAFT && $stream->cms_item_id;
    }

    private function assertPublisherOwnsCamera(LiveStreamCamera $camera, User $user): void
    {
        if ($user->hasAnyRole(self::adminRoles())) {
            return;
        }

        if ((int) $camera->publisher_user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['camera' => 'You can only manage your own camera.']);
        }
    }

    private function disconnectUserCameras(User $user, ?int $exceptStreamId = null): void
    {
        $query = LiveStreamCamera::query()
            ->where('publisher_user_id', $user->id)
            ->whereIn('connection_status', [
                LiveStreamCamera::STATUS_AVAILABLE,
                LiveStreamCamera::STATUS_CONNECTING,
                LiveStreamCamera::STATUS_CONNECTED,
                LiveStreamCamera::STATUS_READY,
                LiveStreamCamera::STATUS_LIVE,
            ]);

        if ($exceptStreamId) {
            $query->where('live_stream_id', '!=', $exceptStreamId);
        }

        $cameras = $query->with('liveStream')->get();

        foreach ($cameras as $camera) {
            $camera->update(['connection_status' => LiveStreamCamera::STATUS_DISCONNECTED]);
            if ($camera->liveStream) {
                $this->broadcastUpdate(
                    $camera->liveStream->fresh(['activeCamera', 'cameras']),
                    'camera_disconnected',
                    $camera->id,
                );
            }
        }
    }

    private function syncMobileCameraStatuses(LiveStream $stream): void
    {
        $activeIds = $this->resolvedActiveCameraIds($stream);

        foreach ($stream->cameras as $camera) {
            if (! $camera->isMobilePublisher() || ! $camera->isOnline()) {
                continue;
            }

            $nextStatus = in_array((int) $camera->id, $activeIds, true)
                ? LiveStreamCamera::STATUS_LIVE
                : LiveStreamCamera::STATUS_READY;

            if ($camera->connection_status !== $nextStatus) {
                $camera->update(['connection_status' => $nextStatus]);
            }
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            LiveStream::STATUS_LIVE => 'Live',
            LiveStream::STATUS_PAUSED => 'Paused',
            LiveStream::STATUS_STOPPED => 'Ended',
            LiveStream::STATUS_SCHEDULED => 'Scheduled',
            LiveStream::STATUS_CANCELLED => 'Cancelled',
            default => 'Draft',
        };
    }

    private function displayStatusLabel(LiveStream $stream): string
    {
        return match ($stream->displayStatus()) {
            'upcoming' => 'Upcoming Live',
            'scheduled' => 'Scheduled',
            'live' => 'Live',
            'paused' => 'Paused',
            'ended' => 'Ended',
            'cancelled' => 'Cancelled',
            default => 'Draft',
        };
    }

    private function countdownSeconds(LiveStream $stream): ?int
    {
        if (! $stream->enable_countdown || ! $stream->scheduled_start_at) {
            return null;
        }

        $tz = $this->timezone();
        $now = now($tz);
        $start = $stream->scheduled_start_at->copy()->timezone($tz);
        $left = (int) $now->diffInSeconds($start, false);

        // Keep serving countdown while wall-clock start is still in the future,
        // even if staff clicked Start Now early (viewers must wait).
        if ($left > 0) {
            return $left;
        }

        return $stream->isUpcoming() ? 0 : null;
    }

    /**
     * Public/parent may watch once broadcasting AND (countdown off OR start time reached).
     * Admin “Start Now” before the clock still waits for the scheduled start.
     */
    private function isViewerScheduleOpen(LiveStream $stream): bool
    {
        if (! $stream->enable_countdown || ! $stream->scheduled_start_at) {
            return true;
        }

        $tz = $this->timezone();
        $now = now($tz);
        $start = $stream->scheduled_start_at->copy()->timezone($tz);

        // 1s grace so clients at 00:00:00 are not blocked by clock skew.
        return $start->lte($now->copy()->addSecond());
    }

    private function extractYoutubeId(string $url): ?string
    {
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|live\/|watch\?v=))([A-Za-z0-9_-]{6,})/', $url, $m)) {
            return $m[1];
        }

        return Str::length($url) <= 20 && ! str_contains($url, '/') ? $url : null;
    }

    private function extractVimeoId(string $url): ?string
    {
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m)) {
            return $m[1];
        }

        return ctype_digit($url) ? $url : null;
    }
}
