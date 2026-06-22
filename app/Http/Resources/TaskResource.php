<?php

namespace App\Http\Resources;

use App\Http\Resources\Json\JalaliResource;

class TaskResource extends JalaliResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        if ($this->relationLoaded('assignee') && $this->assignee) {
            $data['assignee'] = [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ];
        }

        if ($this->relationLoaded('creator') && $this->creator) {
            $data['creator'] = [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ];
        }

        if ($this->relationLoaded('assigner') && $this->assigner) {
            $data['assigner'] = [
                'id' => $this->assigner->id,
                'name' => $this->assigner->name,
            ];
        }

        return $data;
    }
}
