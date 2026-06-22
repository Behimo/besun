<?php

namespace App\Application\Pipeline;

use App\Infrastructure\Persistence\Eloquent\Models\PipelineStageTransition;

class PipelineTransitionLogger
{
    public function log(
        string $entityType,
        int $entityId,
        ?int $fromStageId,
        int $toStageId,
        ?int $userId = null,
    ): void {
        if ($fromStageId === $toStageId) {
            return;
        }

        PipelineStageTransition::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $toStageId,
            'user_id' => $userId ?? auth()->id(),
            'transitioned_at' => now(),
        ]);
    }
}
