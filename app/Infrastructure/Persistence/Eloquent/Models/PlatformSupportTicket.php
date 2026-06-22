<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\PlatformStaff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformSupportTicket extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'creator_staff_id',
        'assignee_staff_id',
        'subject',
        'description',
        'priority',
        'status',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(PlatformStaff::class, 'creator_staff_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(PlatformStaff::class, 'assignee_staff_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PlatformSupportTicketMessage::class, 'ticket_id');
    }
}
