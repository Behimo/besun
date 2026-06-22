<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\PlatformStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'platform_staff_id',
        'action',
        'subject_type',
        'subject_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platformStaff(): BelongsTo
    {
        return $this->belongsTo(PlatformStaff::class);
    }
}
