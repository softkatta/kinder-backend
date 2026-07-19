<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateVariable extends Model
{
    protected $fillable = [
        'key', 'label', 'group', 'data_type', 'applies_to', 'sample_value', 'is_system', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'applies_to' => 'array',
        ];
    }
}
