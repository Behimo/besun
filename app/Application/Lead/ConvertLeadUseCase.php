<?php

namespace App\Application\Lead;

use App\Application\Automation\AutomationDispatcher;
use App\Application\Contact\ContactResolver;
use App\Application\Pipeline\PipelineTransitionLogger;
use App\Application\Product\CrmEntityProductService;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertLeadUseCase
{
    public function __construct(
        protected PipelineTransitionLogger $transitions,
        protected ContactResolver $contacts,
        protected CrmEntityProductService $entityProducts,
        protected AutomationDispatcher $automation,
    ) {}

    public function execute(
        Lead $lead,
        ?int $pipelineStageId = null,
        ?string $dealTitle = null,
        ?float $amount = null,
        ?int $actorId = null,
    ): array {
        if ($lead->status === 'converted') {
            throw ValidationException::withMessages([
                'lead' => ['این لید قبلاً به قیف فروش ارجاع داده شده است.'],
            ]);
        }

        return DB::transaction(function () use ($lead, $pipelineStageId, $dealTitle, $amount, $actorId) {
            $contact = $lead->contact_id
                ? Contact::find($lead->contact_id) ?? $this->contacts->syncLeadToContact($lead)
                : $this->contacts->syncLeadToContact($lead);

            $stageId = $pipelineStageId ?? PipelineStage::query()
                ->where('type', 'sales')
                ->orderBy('sort_order')
                ->value('id');

            if (! $contact->department) {
                $contact->update(['department' => 'sales']);
            }

            $deal = Deal::create([
                'tenant_id' => $lead->tenant_id,
                'workspace_id' => $lead->workspace_id,
                'pipeline_stage_id' => $stageId,
                'contact_id' => $contact->id,
                'lead_id' => $lead->id,
                'title' => $dealTitle ?? 'معامله '.$lead->name,
                'amount' => $amount ?? 0,
                'assigned_to' => $lead->assigned_to,
                'department' => 'sales',
                'next_follow_up_at' => $lead->next_follow_up_at,
                'follow_up_reminder_at' => $lead->follow_up_reminder_at,
            ]);

            $this->transitions->log('deal', $deal->id, null, $stageId);

            $this->entityProducts->copyFromLeadToDeal($lead, $deal);

            $lead->update([
                'status' => 'converted',
                'converted_at' => now(),
                'contact_id' => $contact->id,
            ]);

            $convertedLead = $lead->fresh(['contact']);

            $this->automation->dispatch('lead.converted', $convertedLead, [
                'actor_id' => $actorId,
                'deal_id' => $deal->id,
            ]);

            $this->automation->dispatch('deal.created', $deal, [
                'actor_id' => $actorId,
                'from_lead_conversion' => true,
            ]);

            return [
                'contact' => $contact,
                'deal' => $deal->load(['stage', 'contact']),
                'lead' => $convertedLead,
            ];
        });
    }
}
