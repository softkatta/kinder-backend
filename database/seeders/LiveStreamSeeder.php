<?php

namespace Database\Seeders;

use App\Models\CmsItem;
use App\Models\LiveStream;
use App\Models\LiveStreamCamera;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class LiveStreamSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->first();
        $event = CmsItem::query()->where('type', 'event')->where('slug', 'annual-day')->first();

        $stream = LiveStream::query()->updateOrCreate(
            ['title' => 'Annual Function Live'],
            [
                'tenant_id' => $tenant?->id,
                'description' => 'Multi-camera live coverage of Annual Day celebrations.',
                'cms_item_id' => $event?->id,
                'status' => LiveStream::STATUS_DRAFT,
            ],
        );

        $cameras = [
            [
                'name' => 'Camera 1 — Main Stage',
                'location' => 'Auditorium Stage',
                'stream_type' => LiveStreamCamera::TYPE_YOUTUBE,
                'stream_url' => 'https://www.youtube.com/watch?v=jfKfPfyJRdk',
                'display_order' => 0,
            ],
            [
                'name' => 'Camera 2 — Audience',
                'location' => 'Hall Seating',
                'stream_type' => LiveStreamCamera::TYPE_YOUTUBE,
                'stream_url' => 'https://www.youtube.com/watch?v=DWcJFNfaw9c',
                'display_order' => 1,
            ],
            [
                'name' => 'Camera 3 — Dance Performance',
                'location' => 'Stage Left',
                'stream_type' => LiveStreamCamera::TYPE_YOUTUBE,
                'stream_url' => 'https://www.youtube.com/watch?v=5qap5aO4i9A',
                'display_order' => 2,
            ],
            [
                'name' => 'Camera 4 — Prize Distribution',
                'location' => 'Stage Right',
                'stream_type' => LiveStreamCamera::TYPE_YOUTUBE,
                'stream_url' => 'https://www.youtube.com/watch?v=21X5lGlDOfg',
                'display_order' => 3,
            ],
        ];

        foreach ($cameras as $camera) {
            LiveStreamCamera::query()->updateOrCreate(
                [
                    'live_stream_id' => $stream->id,
                    'name' => $camera['name'],
                ],
                [
                    ...$camera,
                    'is_enabled' => true,
                ],
            );
        }

        $first = $stream->cameras()->orderBy('display_order')->first();
        if ($first) {
            $stream->update(['active_camera_id' => $first->id]);
        }
    }
}
