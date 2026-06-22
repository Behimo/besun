<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SmsMessage extends TenantWorkspaceModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'user_id',
        'type',
        'from_number',
        'body',
        'pattern_code',
        'recipients_count',
        'ippanel_outbox_ids',
        'status',
        'related_type',
        'related_id',
        'scheduled_at',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'ippanel_outbox_ids' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(SmsMessageRecipient::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
