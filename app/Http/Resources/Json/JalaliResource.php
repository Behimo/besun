<?php

namespace App\Http\Resources\Json;

use Illuminate\Http\Resources\Json\JsonResource;

class JalaliResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        foreach (['created_at', 'updated_at', 'trial_ends_at', 'starts_at', 'ends_at', 'expires_at', 'due_at', 'completed_at', 'work_started_at', 'work_ended_at', 'reminder_at', 'next_follow_up_at', 'follow_up_reminder_at', 'scheduled_at', 'report_date', 'submitted_at', 'reviewed_at'] as $field) {
            if (isset($data[$field]) && $data[$field]) {
                $data[$field.'_jalali'] = persianDateShort($data[$field]);
            }
        }

        return $data;
    }
}
