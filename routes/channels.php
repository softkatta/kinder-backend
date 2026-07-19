<?php

use App\Models\LiveStream;
use App\Models\User;
use App\Services\LiveStream\LiveStreamService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('live-stream.{streamId}', function (User $user, int $streamId) {
    if (! $user->hasAnyRole(array_merge(LiveStreamService::staffRoles(), LiveStreamService::viewerRoles()))) {
        return false;
    }

    return LiveStream::query()->whereKey($streamId)->exists();
});

Broadcast::channel('live-events', function (User $user) {
    return $user->hasAnyRole(array_merge(
        LiveStreamService::staffRoles(),
        LiveStreamService::viewerRoles(),
    ));
});

Broadcast::channel('admin-notifications.{userId}', function (User $user, int $userId) {
    return (int) $user->id === $userId
        && $user->hasAnyRole(['super_admin', 'admin']);
});
