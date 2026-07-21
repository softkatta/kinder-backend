<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CmsItem;
use App\Models\LiveStream;
use App\Models\LiveStreamCamera;
use App\Services\LiveStream\LiveKitService;
use App\Services\LiveStream\LiveStreamService;
use App\Services\LiveStream\LiveStreamUrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LiveStreamController extends Controller
{
    public function __construct(
        private readonly LiveStreamService $streams,
        private readonly LiveStreamUrlValidator $urlValidator,
        private readonly LiveKitService $liveKit,
    ) {}

    public function index(): JsonResponse
    {
        $items = LiveStream::query()
            ->with(['cameras', 'activeCamera'])
            ->latest()
            ->get()
            ->map(fn (LiveStream $s) => $this->streams->toStaffPayload($s));

        return ApiResponse::success($items);
    }

    public function cmsEvents(): JsonResponse
    {
        return ApiResponse::success($this->streams->cmsEventsForLiveStream());
    }

    public function linkFromCms(CmsItem $cmsItem): JsonResponse
    {
        $stream = $this->streams->linkFromCms($cmsItem);
        $message = $stream->wasRecentlyCreated ? 'Event linked for live streaming' : 'Event already linked';

        return ApiResponse::success(
            $this->streams->toStaffPayload($stream),
            $message,
            $stream->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable', 'string', 'max:500'],
            'cms_item_id' => ['nullable', 'integer', 'exists:cms_items,id'],
            'mode' => ['nullable', Rule::in(['instant', 'scheduled'])],
            'status' => ['nullable', Rule::in(['draft', 'scheduled'])],
        ]);

        $stream = $this->streams->createStream($data);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Live stream created', 201);
    }

    public function show(LiveStream $liveStream): JsonResponse
    {
        return ApiResponse::success($this->streams->toStaffPayload($liveStream));
    }

    public function update(Request $request, LiveStream $liveStream): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable', 'string', 'max:500'],
            'cms_item_id' => ['nullable', 'integer', 'exists:cms_items,id'],
            'event_date' => ['nullable', 'date'],
            'scheduled_start_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after:scheduled_start_at'],
            'stream_source' => ['nullable', 'string', 'max:30'],
            'enable_countdown' => ['sometimes', 'boolean'],
            'enable_reminder' => ['sometimes', 'boolean'],
            'notify_before_minutes' => ['nullable', 'array'],
            'notify_before_minutes.*' => ['integer', 'min:1', 'max:1440'],
            'visibility' => ['nullable', Rule::in(['public', 'parents_only'])],
            'auto_start' => ['sometimes', 'boolean'],
            'auto_end' => ['sometimes', 'boolean'],
            'audio_enabled' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'stopped'])],
        ]);

        $stream = $this->streams->updateStream($liveStream, $data);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Updated');
    }

    public function destroy(LiveStream $liveStream): JsonResponse
    {
        $this->streams->deletePermanently($liveStream);

        return ApiResponse::success(null, 'Live stream permanently deleted');
    }

    public function schedule(Request $request, LiveStream $liveStream): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'banner' => ['nullable', 'string', 'max:500'],
            'event_date' => ['nullable', 'date'],
            'scheduled_start_at' => ['required', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after:scheduled_start_at'],
            'stream_source' => ['nullable', 'string', 'max:30'],
            'enable_countdown' => ['sometimes', 'boolean'],
            'enable_reminder' => ['sometimes', 'boolean'],
            'notify_before_minutes' => ['nullable', 'array'],
            'notify_before_minutes.*' => ['integer', 'min:1', 'max:1440'],
            'visibility' => ['nullable', Rule::in(['public', 'parents_only'])],
            'auto_start' => ['sometimes', 'boolean'],
            'auto_end' => ['sometimes', 'boolean'],
        ]);

        $stream = $this->streams->scheduleStream($liveStream, $data);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Live event scheduled');
    }

    public function cancel(LiveStream $liveStream): JsonResponse
    {
        $stream = $this->streams->cancelStream($liveStream);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Live event cancelled');
    }

    public function storeCamera(Request $request, LiveStream $liveStream): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'stream_type' => ['required', Rule::in(['hls', 'youtube', 'vimeo', 'embed', 'facebook', 'rtmp', 'builtin_camera'])],
            'stream_url' => ['nullable', 'string', 'max:2048'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $this->urlValidator->validate($data['stream_type'], $data['stream_url'] ?? '');

        $order = (int) $liveStream->cameras()->max('display_order') + 1;
        $camera = $liveStream->cameras()->create([
            ...$data,
            'stream_url' => $data['stream_url'] ?? ($data['stream_type'] === 'builtin_camera' ? 'builtin://camera' : ''),
            'display_order' => $order,
            'is_enabled' => $data['is_enabled'] ?? true,
        ]);

        if (! $liveStream->active_camera_id && $camera->is_enabled) {
            $liveStream->update([
                'active_camera_id' => $camera->id,
                'active_camera_ids' => [(int) $camera->id],
                'layout_mode' => \App\Models\LiveStream::normalizeLayoutMode((int) ($liveStream->layout_mode ?? 1)),
            ]);
        }

        $this->streams->broadcastUpdate($liveStream->fresh(), 'camera_added');

        return ApiResponse::success($this->streams->toStaffPayload($liveStream->fresh()), 'Camera added', 201);
    }

    public function updateCamera(Request $request, LiveStream $liveStream, LiveStreamCamera $camera): JsonResponse
    {
        $this->assertCameraBelongsToStream($liveStream, $camera);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'stream_type' => ['sometimes', Rule::in(['hls', 'youtube', 'vimeo', 'embed', 'facebook', 'rtmp', 'builtin_camera'])],
            'stream_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['stream_type'], $data['stream_url'])) {
            $this->urlValidator->validate($data['stream_type'], $data['stream_url'] ?? '');
        } elseif (isset($data['stream_url'])) {
            $this->urlValidator->validate($camera->stream_type, $data['stream_url'] ?? '');
        }

        $camera->update($data);

        if (! $camera->is_enabled && (int) $liveStream->active_camera_id === (int) $camera->id) {
            $next = $liveStream->cameras()->where('is_enabled', true)->orderBy('display_order')->first();
            $this->streams->setActiveCamerasAfterRemoval($liveStream, (int) $camera->id, $next?->id);
        } elseif (! $camera->is_enabled) {
            $this->streams->setActiveCamerasAfterRemoval($liveStream, (int) $camera->id, $liveStream->active_camera_id);
        }

        $this->streams->broadcastUpdate($liveStream->fresh(), 'camera_updated');

        return ApiResponse::success($this->streams->toStaffPayload($liveStream->fresh()), 'Camera updated');
    }

    public function destroyCamera(LiveStream $liveStream, LiveStreamCamera $camera): JsonResponse
    {
        $this->assertCameraBelongsToStream($liveStream, $camera);

        if ((int) $liveStream->active_camera_id === (int) $camera->id) {
            $next = $liveStream->cameras()->where('id', '!=', $camera->id)->where('is_enabled', true)->orderBy('display_order')->first();
            $this->streams->setActiveCamerasAfterRemoval($liveStream, (int) $camera->id, $next?->id);
        } else {
            $this->streams->setActiveCamerasAfterRemoval($liveStream, (int) $camera->id, $liveStream->active_camera_id);
        }

        $camera->delete();
        $this->streams->broadcastUpdate($liveStream->fresh(), 'camera_removed');

        return ApiResponse::success($this->streams->toStaffPayload($liveStream->fresh()), 'Camera removed');
    }

    public function reorderCameras(Request $request, LiveStream $liveStream): JsonResponse
    {
        $data = $request->validate([
            'camera_ids' => ['required', 'array', 'min:1'],
            'camera_ids.*' => ['integer'],
        ]);

        $stream = $this->streams->reorderCameras($liveStream, $data['camera_ids']);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Cameras reordered');
    }

    public function setActiveCamera(Request $request, LiveStream $liveStream): JsonResponse
    {
        $data = $request->validate([
            'camera_id' => ['required', 'integer'],
        ]);

        $stream = $this->streams->setActiveCamera($liveStream, (int) $data['camera_id']);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Active camera updated');
    }

    public function setActiveCameras(Request $request, LiveStream $liveStream): JsonResponse
    {
        $data = $request->validate([
            'camera_ids' => ['required', 'array', 'min:1', 'max:4'],
            'camera_ids.*' => ['integer'],
        ]);

        $stream = $this->streams->setActiveCameras($liveStream, $data['camera_ids']);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Layout cameras updated');
    }

    public function setLayout(Request $request, LiveStream $liveStream): JsonResponse
    {
        $data = $request->validate([
            'layout_mode' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $stream = $this->streams->setLayoutMode($liveStream, (int) $data['layout_mode']);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Player layout updated');
    }

    public function start(LiveStream $liveStream): JsonResponse
    {
        $stream = $this->streams->startLive($liveStream, notify: true);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Live started');
    }

    public function pause(LiveStream $liveStream): JsonResponse
    {
        $stream = $this->streams->pauseLive($liveStream);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Live paused');
    }

    public function resume(LiveStream $liveStream): JsonResponse
    {
        $stream = $this->streams->resumeLive($liveStream);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Live resumed');
    }

    public function stop(LiveStream $liveStream): JsonResponse
    {
        $stream = $this->streams->stopLive($liveStream);

        return ApiResponse::success($this->streams->toStaffPayload($stream), 'Live ended');
    }

    public function previewCamera(LiveStream $liveStream, LiveStreamCamera $camera): JsonResponse
    {
        $this->assertCameraBelongsToStream($liveStream, $camera);

        return ApiResponse::success([
            'camera_id' => $camera->id,
            'name' => $camera->name,
            'stream_type' => $camera->stream_type,
            'preview' => [
                ...$this->streams->playbackConfig($liveStream, $camera),
                'audio_muted' => (bool) $camera->audio_muted,
                'audio_volume' => max(0, min(100, (int) ($camera->audio_volume ?? 100))),
            ],
        ]);
    }

    public function viewerActive(): JsonResponse
    {
        $this->streams->processAutoStartEnd();

        $stream = $this->streams->findActiveForViewer(publicOnly: false);

        if ($stream) {
            return ApiResponse::success($this->streams->toViewerPayload($stream));
        }

        $upcoming = $this->streams->findScheduledForViewer(publicOnly: false);
        if ($upcoming) {
            return ApiResponse::success($this->streams->toViewerPayload($upcoming));
        }

        return ApiResponse::success(null, 'No active live stream');
    }

    public function viewerUpcoming(): JsonResponse
    {
        $items = $this->streams->findUpcomingForViewer(publicOnly: false)
            ->map(fn (LiveStream $s) => $this->streams->toViewerPayload($s));

        return ApiResponse::success($items);
    }

    public function watch(LiveStream $liveStream): JsonResponse
    {
        return ApiResponse::success($this->streams->toWatchPayload($liveStream));
    }

    public function publicActive(): JsonResponse
    {
        $this->streams->processAutoStartEnd();

        $stream = $this->streams->findActiveForViewer(publicOnly: true);

        if ($stream) {
            return ApiResponse::success($this->streams->toViewerPayload($stream));
        }

        $upcoming = $this->streams->findScheduledForViewer(publicOnly: true);
        if ($upcoming) {
            return ApiResponse::success($this->streams->toViewerPayload($upcoming));
        }

        return ApiResponse::success(null, 'No active live stream');
    }

    public function publicUpcoming(): JsonResponse
    {
        $items = $this->streams->findUpcomingForViewer(publicOnly: true)
            ->map(fn (LiveStream $s) => $this->streams->toViewerPayload($s));

        return ApiResponse::success($items);
    }

    public function publicWatch(LiveStream $liveStream): JsonResponse
    {
        if ($liveStream->visibility === LiveStream::VISIBILITY_PARENTS_ONLY) {
            return ApiResponse::error('Login required to watch this stream', 403);
        }

        return ApiResponse::success($this->streams->toWatchPayload($liveStream));
    }

    public function publisherEvents(): JsonResponse
    {
        return ApiResponse::success($this->streams->publisherEventsForUser());
    }

    public function joinCamera(Request $request, LiveStream $liveStream): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $camera = $this->streams->joinCamera($liveStream, $request->user(), $data);

        return ApiResponse::success(
            $this->streams->toPublisherJoinPayload($liveStream->fresh(), $camera),
            'Camera session started',
            201,
        );
    }

    public function updateCameraSession(Request $request, LiveStream $liveStream, LiveStreamCamera $camera): JsonResponse
    {
        $this->assertCameraBelongsToStream($liveStream, $camera);

        $data = $request->validate([
            'connection_status' => ['sometimes', Rule::in(LiveStreamCamera::connectionStatuses())],
            'device_name' => ['nullable', 'string', 'max:255'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $camera = $this->streams->updateCameraSession($liveStream, $camera, $request->user(), $data);

        return ApiResponse::success(
            $this->streams->toPublisherJoinPayload($liveStream->fresh(), $camera),
            'Session updated',
        );
    }

    public function disconnectCamera(Request $request, LiveStream $liveStream, LiveStreamCamera $camera): JsonResponse
    {
        $this->assertCameraBelongsToStream($liveStream, $camera);

        $adminForce = $request->user()->hasAnyRole(LiveStreamService::adminRoles());
        $camera = $this->streams->disconnectCamera($liveStream, $camera, $request->user(), $adminForce);

        if ($adminForce) {
            return ApiResponse::success($this->streams->toStaffPayload($liveStream->fresh()), 'Camera disconnected');
        }

        return ApiResponse::success(
            $this->streams->toPublisherJoinPayload($liveStream->fresh(), $camera),
            'Camera disconnected',
        );
    }

    public function muteCamera(Request $request, LiveStream $liveStream, LiveStreamCamera $camera): JsonResponse
    {
        $this->assertCameraBelongsToStream($liveStream, $camera);

        $data = $request->validate([
            'muted' => ['required', 'boolean'],
        ]);

        $camera = $this->streams->setCameraAudioMuted($liveStream, $camera, (bool) $data['muted']);

        return ApiResponse::success($this->streams->toStaffPayload($liveStream->fresh()), $data['muted'] ? 'Audio muted' : 'Audio unmuted');
    }

    public function volumeCamera(Request $request, LiveStream $liveStream, LiveStreamCamera $camera): JsonResponse
    {
        $this->assertCameraBelongsToStream($liveStream, $camera);

        $data = $request->validate([
            'volume' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $this->streams->setCameraAudioVolume($liveStream, $camera, (int) $data['volume']);

        return ApiResponse::success(
            $this->streams->toStaffPayload($liveStream->fresh()),
            'Camera volume updated',
        );
    }

    public function livekitConfig(): JsonResponse
    {
        return ApiResponse::success($this->liveKit->publicConfig());
    }

    public function webrtcToken(Request $request, LiveStream $liveStream): JsonResponse
    {
        $data = $request->validate([
            'camera_id' => ['nullable', 'integer'],
            'role' => ['nullable', Rule::in(['publisher', 'viewer'])],
        ]);

        $user = $request->user();
        $user?->loadMissing('roles');
        $role = $data['role'] ?? 'viewer';

        if (! $this->liveKit->isConfigured()) {
            return ApiResponse::error(
                'Built-in camera streaming is not configured. Enable LiveKit in Admin → Settings → LiveKit (URL, API key, secret), then start the LiveKit server.',
                503,
            );
        }

        if (! $this->liveKit->isReachable()) {
            return ApiResponse::error(
                'LiveKit server is not running on '.config('livekit.url').'. From backend/: run powershell -File scripts/start-livekit.ps1 (or docker compose -f docker-compose.livekit.yml up -d).',
                503,
            );
        }

        if ($role === 'publisher') {
            if (! $user || ! $user->hasAnyRole(LiveStreamService::staffRoles())) {
                return ApiResponse::error('Forbidden', 403);
            }

            $cameraId = (int) ($data['camera_id'] ?? $liveStream->active_camera_id);
            $camera = $liveStream->cameras()->whereKey($cameraId)->first();
            if (! $camera || $camera->stream_type !== LiveStreamCamera::TYPE_BUILTIN) {
                return ApiResponse::error('Built-in camera not found', 404);
            }

            if ($camera->publisher_user_id && ! $user->hasAnyRole(LiveStreamService::adminRoles())) {
                if ((int) $camera->publisher_user_id !== (int) $user->id) {
                    return ApiResponse::error('You can only publish from your own camera', 403);
                }
            }

            $token = $this->liveKit->createPublisherToken($liveStream, $camera, $user->name);

            return ApiResponse::success([
                'token' => $token,
                'url' => config('livekit.url'),
                'room_name' => $this->liveKit->roomName($liveStream),
                'participant_identity' => $this->liveKit->participantIdentity($camera),
            ]);
        }

        if ($liveStream->visibility === LiveStream::VISIBILITY_PARENTS_ONLY) {
            if (! $user || ! $user->hasAnyRole(LiveStreamService::liveAccessRoles())) {
                return ApiResponse::error('Login required', 403);
            }
        }

        if ($role !== 'publisher' && ! $liveStream->isBroadcasting()) {
            return ApiResponse::error('Stream is not live yet. Start the live event before viewers can connect.', 422);
        }

        $viewerId = $user ? 'viewer-'.$user->id : 'guest-'.Str::random(8);
        $token = $this->liveKit->createViewerToken($liveStream, $viewerId);

        $camera = $liveStream->activeCamera;

        return ApiResponse::success([
            'token' => $token,
            'url' => config('livekit.url'),
            'room_name' => $this->liveKit->roomName($liveStream),
            'participant_identity' => $camera
                ? $this->liveKit->participantIdentity($camera)
                : null,
        ]);
    }

    public function publicWebrtcToken(Request $request, LiveStream $liveStream): JsonResponse
    {
        if ($liveStream->visibility === LiveStream::VISIBILITY_PARENTS_ONLY) {
            return ApiResponse::error('Login required to watch this stream', 403);
        }

        $request->merge(['role' => 'viewer']);

        return $this->webrtcToken($request, $liveStream);
    }

    public function playback(Request $request, LiveStream $liveStream): RedirectResponse|JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return ApiResponse::error('Invalid or expired playback link', 403);
        }

        $cameraId = (int) $request->query('camera');
        $camera = $this->streams->resolvePlaybackCamera($liveStream, $cameraId);

        if (! $camera) {
            return ApiResponse::error('Playback unavailable', 403);
        }

        return redirect()->away($camera->stream_url);
    }

    private function assertCameraBelongsToStream(LiveStream $liveStream, LiveStreamCamera $camera): void
    {
        if ((int) $camera->live_stream_id !== (int) $liveStream->id) {
            abort(404);
        }
    }
}
