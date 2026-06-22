<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesTargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'department' => $this->department,
            'user_id' => $this->user_id,
            'jyear' => $this->jyear,
            'jmonth' => $this->jmonth,
            'revenue_target' => (float) $this->revenue_target,
            'deals_target' => $this->deals_target,
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'set_by' => $this->whenLoaded('setter', fn () => [
                'id' => $this->setter->id,
                'name' => $this->setter->name,
            ]),
        ];
    }
}
