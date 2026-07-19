<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    protected $fillable = [
        'cms_item_id', 'full_name', 'email', 'phone', 'qualification',
        'experience_years', 'cover_letter', 'resume_path', 'status',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(CmsItem::class, 'cms_item_id');
    }
}
