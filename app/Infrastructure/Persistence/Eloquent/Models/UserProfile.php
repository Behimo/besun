<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'job_title',
        'city',
        'bio',
        'skills',
        'visible_to_owners',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'visible_to_owners' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
