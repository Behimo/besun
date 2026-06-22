<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyWorkEntry extends Model
{
    protected $fillable = [
        'daily_work_report_id',
        'title',
        'description',
        'minutes',
        'effort_score',
        'task_id',
        'sort_order',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyWorkReport::class, 'daily_work_report_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
