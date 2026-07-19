<?php

namespace App\Events;

use App\Models\LiveStream;
use App\Services\LiveStream\LiveStreamService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveStreamUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LiveStream $stream,
        public string $action,
        public ?int $cameraId = null,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('live-stream.'.$this->stream->id),
            new PrivateChannel('live-events'),
            new Channel('live-public'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stream.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $service = app(LiveStreamService::class);
        $this->stream->loadMissing(['activeCamera', 'cameras']);

        return [
            'action' => $this->action,
            'stream_id' => $this->stream->id,
            'camera_id' => $this->cameraId,
            'viewer' => $service->toViewerPayload($this->stream),
            'watch' => $service->toWatchPayload($this->stream),
            'staff' => $service->toStaffPayload($this->stream),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
