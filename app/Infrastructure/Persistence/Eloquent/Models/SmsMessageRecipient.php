<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessageRecipient extends Model
{
    protected $fillable = [
        'sms_message_id',
        'phone',
        'lead_id',
        'contact_id',
        'delivery_status',
        'ippanel_meta',
    ];

    protected function casts(): array
    {
        return [
            'ippanel_meta' => 'array',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SmsMessage::class, 'sms_message_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
