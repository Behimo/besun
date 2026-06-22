<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\PlatformStaff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSupportTicketMessage extends Model
{
    protected $fillable = [
        'ticket_id',
        'platform_staff_id',
        'body',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(PlatformSupportTicket::class, 'ticket_id');
    }

    public function platformStaff(): BelongsTo
    {
        return $this->belongsTo(PlatformStaff::class);
    }
}
