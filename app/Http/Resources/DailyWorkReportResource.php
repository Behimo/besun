<?php

namespace App\Http\Resources;

use App\Application\DailyWork\DailyWorkReportService;
use App\Http\Resources\Json\JalaliResource;
use Illuminate\Http\Request;

class DailyWorkReportResource extends JalaliResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        if ($this->relationLoaded('user') && $this->user) {
            $data['user'] = [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ];
        }

        if ($this->relationLoaded('reviewer') && $this->reviewer) {
            $data['reviewer'] = [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ];
        }

        $data['is_reviewed'] = $this->manager_score !== null;
        $data['can_edit'] = $this->canEditForRequest($request);

        if ($this->relationLoaded('entries')) {
            $data['entries'] = $this->entries->map(fn ($entry) => [
                'id' => $entry->id,
                'title' => $entry->title,
                'description' => $entry->description,
                'minutes' => $entry->minutes,
                'effort_score' => $entry->effort_score,
                'task_id' => $entry->task_id,
                'sort_order' => $entry->sort_order,
            ])->values()->all();
        }

        return $data;
    }

    protected function canEditForRequest(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        return app(DailyWorkReportService::class)->canEdit($user, $this->resource);
    }
}
