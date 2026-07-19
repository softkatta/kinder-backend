<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CmsItem extends Model
{
    protected $fillable = [
        'tenant_id', 'type', 'slug', 'title', 'summary', 'body', 'image', 'meta', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function liveStream(): HasOne
    {
        return $this->hasOne(LiveStream::class);
    }

    public function toPublicArray(?string $locale = 'en'): array
    {
        $locale = in_array($locale, ['en', 'mr'], true) ? $locale : 'en';
        $meta = $this->meta ?? [];

        $title = $this->localizedField('title', $locale);
        $summary = $this->localizedField('summary', $locale);
        $body = $this->localizedField('body', $locale);

        return array_merge($this->localizedMeta($meta, $locale), [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $title,
            'summary' => $summary,
            'description' => $summary,
            'image' => $this->image,
            'body' => $body,
            'detail' => $body,
            'question' => $title,
            'answer' => $body,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
        ]);
    }

    private function localizedField(string $field, string $locale): ?string
    {
        $meta = $this->meta ?? [];

        if ($locale === 'mr') {
            $mr = $meta["{$field}_mr"] ?? null;
            if (is_string($mr) && $mr !== '') {
                return $mr;
            }
        }

        if ($locale === 'en') {
            $en = $meta["{$field}_en"] ?? null;
            if (is_string($en) && $en !== '') {
                return $en;
            }
        }

        $value = $this->{$field};

        if ($locale === 'mr' && is_string($value) && trim($value) !== '') {
            $translated = app(\App\Services\TranslationService::class)->translate($value, 'mr', 'en');
            if (is_string($translated) && $translated !== '') {
                return $translated;
            }
        }

        return $value !== null ? (string) $value : null;
    }

    /** @param array<string, mixed> $meta */
    private function localizedMeta(array $meta, string $locale): array
    {
        $out = [];

        foreach ($meta as $key => $value) {
            if (str_ends_with($key, '_mr') || str_ends_with($key, '_en')) {
                continue;
            }

            if ($locale === 'mr' && isset($meta["{$key}_mr"]) && is_string($meta["{$key}_mr"]) && $meta["{$key}_mr"] !== '') {
                $out[$key] = $meta["{$key}_mr"];
            } elseif ($locale === 'en' && isset($meta["{$key}_en"]) && is_string($meta["{$key}_en"]) && $meta["{$key}_en"] !== '') {
                $out[$key] = $meta["{$key}_en"];
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
