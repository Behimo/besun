<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsCategory extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'sort_order'];

    public function posts(): HasMany
    {
        return $this->hasMany(CmsPost::class, 'category_id');
    }
}
