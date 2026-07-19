<?php

namespace App\Services\Cms;

use App\Models\CmsItem;
use App\Models\LiveStream;
use App\Services\LiveStream\LiveStreamService;
use Illuminate\Support\Facades\Storage;

class CmsDeletionService
{
    public function __construct(
        private readonly LiveStreamService $liveStreams,
    ) {}

    public function deletePermanently(CmsItem $item, bool $deleteLinkedStream = true): void
    {
        if ($deleteLinkedStream) {
            $stream = LiveStream::query()->where('cms_item_id', $item->id)->first();
            if ($stream) {
                $this->liveStreams->deletePermanently($stream, deleteCms: false);
            }
        }

        $this->deleteStoredAssets($item);
        $item->delete();
    }

    private function deleteStoredAssets(CmsItem $item): void
    {
        $paths = array_filter([
            $item->image,
            is_array($item->meta) ? ($item->meta['image'] ?? null) : null,
        ]);

        foreach ($paths as $path) {
            $this->deleteStoragePath($path);
        }
    }

    private function deleteStoragePath(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        if ($normalized !== '') {
            Storage::disk('public')->delete($normalized);
        }
    }
}
