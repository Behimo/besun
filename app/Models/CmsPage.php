<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    protected $fillable = [
        'slug', 'title', 'template', 'meta_title', 'meta_description', 'meta_keywords',
        'og_image', 'robots', 'content', 'is_published', 'show_in_nav', 'is_system', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_published' => 'boolean',
            'show_in_nav' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
