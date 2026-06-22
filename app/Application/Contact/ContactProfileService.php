<?php

namespace App\Application\Contact;

use App\Application\Crm\CrmHandoffService;
use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\CrmEntityProduct;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\Product;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStageTransition;
use App\Infrastructure\Persistence\Eloquent\Models\Task;
use App\Models\User;

class ContactProfileService
{
    public function __construct(
        protected CrmHandoffService $handoffs,
    ) {}

    public function build(Contact $contact, ?User $viewer = null): array
    {
        $contact->load(['assignee:id,name']);

        $leads = Lead::with([
            'campaign',
            'marketingStage',
            'assignee:id,name',
            'products' => fn ($q) => $q->select('products.id', 'products.name', 'products.sku', 'products.image_url'),
        ])
            ->where('contact_id', $contact->id)
            ->orderByDesc('updated_at')
            ->get();

        $activeLead = $leads->firstWhere('status', '!=', 'converted');

        $readyForSalesStageId = PipelineStage::query()
            ->where('type', 'marketing')
            ->where('is_lost', false)
            ->orderByDesc('sort_order')
            ->value('id');

        $deals = Deal::with([
            'stage',
            'assignee:id,name',
            'products' => fn ($q) => $q->select('products.id', 'products.name', 'products.sku', 'products.image_url'),
        ])
            ->where('contact_id', $contact->id)
            ->orderByDesc('updated_at')
            ->get();

        $openDeals = $deals->filter(fn ($deal) => ! $deal->stage?->is_won && ! $deal->stage?->is_lost);

        $leadIds = $leads->pluck('id');
        $dealIds = $deals->pluck('id');

        $activities = Activity::with('user:id,name')
            ->where(function ($q) use ($contact, $leadIds, $dealIds) {
                $q->where(function ($inner) use ($contact) {
                    $inner->where('related_type', Contact::class)
                        ->where('related_id', $contact->id);
                });

                if ($leadIds->isNotEmpty()) {
                    $q->orWhere(function ($inner) use ($leadIds) {
                        $inner->where('related_type', Lead::class)
                            ->whereIn('related_id', $leadIds);
                    });
                }

                if ($dealIds->isNotEmpty()) {
                    $q->orWhere(function ($inner) use ($dealIds) {
                        $inner->where('related_type', Deal::class)
                            ->whereIn('related_id', $dealIds);
                    });
                }
            })
            ->orderByDesc('happened_at')
            ->orderByDesc('scheduled_at')
            ->limit(50)
            ->get();

        $tasks = Task::with(['assignee:id,name'])
            ->where(function ($q) use ($contact, $leadIds, $dealIds) {
                $q->where(function ($inner) use ($contact) {
                    $inner->where('related_type', Contact::class)
                        ->where('related_id', $contact->id);
                });

                if ($leadIds->isNotEmpty()) {
                    $q->orWhere(function ($inner) use ($leadIds) {
                        $inner->where('related_type', Lead::class)
                            ->whereIn('related_id', $leadIds);
                    });
                }

                if ($dealIds->isNotEmpty()) {
                    $q->orWhere(function ($inner) use ($dealIds) {
                        $inner->where('related_type', Deal::class)
                            ->whereIn('related_id', $dealIds);
                    });
                }
            })
            ->orderByDesc('due_at')
            ->limit(30)
            ->get();

        $transitions = PipelineStageTransition::with(['fromStage', 'toStage', 'user:id,name'])
            ->where(function ($q) use ($leadIds, $dealIds) {
                $q->where(function ($inner) use ($leadIds) {
                    $inner->where('entity_type', 'lead')
                        ->whereIn('entity_id', $leadIds);
                })->orWhere(function ($inner) use ($dealIds) {
                    $inner->where('entity_type', 'deal')
                        ->whereIn('entity_id', $dealIds);
                });
            })
            ->when($leadIds->isEmpty() && $dealIds->isEmpty(), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('transitioned_at')
            ->limit(40)
            ->get();

        return [
            'contact' => $this->formatContact($contact),
            'active_lead' => $activeLead ? $this->formatLead($activeLead, $readyForSalesStageId) : null,
            'leads' => $leads->map(fn ($l) => $this->formatLead($l, $readyForSalesStageId)),
            'deals' => $deals->map(fn ($d) => $this->formatDeal($d)),
            'products' => $this->aggregateProducts($contact, $leads, $deals),
            'funnel_products' => $this->buildFunnelProducts($activeLead, $openDeals, $readyForSalesStageId),
            'activities' => $activities->map(fn ($a) => $this->formatActivity($a)),
            'tasks' => $tasks->map(fn ($t) => $this->formatTask($t)),
            'pending_handoffs' => $viewer ? $this->handoffs->pendingForUser($viewer, $contact->id) : [],
            'timeline' => $this->buildTimeline($contact, $leads, $deals, $activities, $tasks, $transitions),
            'stats' => [
                'leads_count' => $leads->count(),
                'deals_count' => $deals->count(),
                'deals_total_amount' => (float) $deals->sum('amount'),
                'open_tasks' => $tasks->where('status', '!=', 'completed')->count(),
                'activities_count' => $activities->count(),
            ],
        ];
    }

    protected function formatContact(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'company' => $contact->company,
            'job_title' => $contact->job_title,
            'city' => $contact->city,
            'notes' => $contact->notes,
            'tags' => $contact->tags ?? [],
            'custom_fields' => $contact->custom_fields ?? [],
            'assigned_to' => $contact->assigned_to,
            'assignee' => $contact->assignee ? ['id' => $contact->assignee->id, 'name' => $contact->assignee->name] : null,
            'created_at' => $contact->created_at?->toIso8601String(),
            'created_at_jalali' => persianDateShort($contact->created_at),
        ];
    }

    protected function formatLead(Lead $lead, ?int $readyForSalesStageId = null): array
    {
        return [
            'id' => $lead->id,
            'contact_id' => $lead->contact_id,
            'name' => $lead->name,
            'status' => $lead->status,
            'score' => $lead->score,
            'source' => $lead->source,
            'campaign' => $lead->campaign?->only(['id', 'name']),
            'marketing_stage' => $lead->marketingStage?->only(['id', 'name', 'color']),
            'is_ready_for_sales' => $readyForSalesStageId
                ? $lead->marketing_stage_id === $readyForSalesStageId
                : false,
            'assignee' => $lead->assignee ? ['id' => $lead->assignee->id, 'name' => $lead->assignee->name] : null,
            'products' => $this->formatProducts($lead->products ?? collect()),
            'next_follow_up_at' => $lead->next_follow_up_at?->toIso8601String(),
            'converted_at' => $lead->converted_at?->toIso8601String(),
            'notes' => $lead->notes,
            'updated_at' => $lead->updated_at?->toIso8601String(),
        ];
    }

    protected function formatDeal(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'contact_id' => $deal->contact_id,
            'lead_id' => $deal->lead_id,
            'title' => $deal->title,
            'amount' => $deal->amount,
            'currency' => $deal->currency,
            'stage' => $deal->stage?->only(['id', 'name', 'color']),
            'assignee' => $deal->assignee ? ['id' => $deal->assignee->id, 'name' => $deal->assignee->name] : null,
            'products' => $this->formatProducts($deal->products ?? collect()),
            'next_follow_up_at' => $deal->next_follow_up_at?->toIso8601String(),
            'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
            'notes' => $deal->notes,
            'updated_at' => $deal->updated_at?->toIso8601String(),
        ];
    }

    protected function aggregateProducts(Contact $contact, $leads, $deals): array
    {
        $productIds = collect();

        foreach ($leads as $lead) {
            $productIds = $productIds->merge($lead->products?->pluck('id') ?? []);
        }
        foreach ($deals as $deal) {
            $productIds = $productIds->merge($deal->products?->pluck('id') ?? []);
        }

        $directIds = CrmEntityProduct::query()
            ->where('entity_type', 'contact')
            ->where('entity_id', $contact->id)
            ->pluck('product_id');

        $ids = $productIds->merge($directIds)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'sku', 'image_url', 'price', 'currency'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'image_url' => $p->image_url,
                'price' => $p->price,
                'currency' => $p->currency,
            ])
            ->values()
            ->all();
    }

    protected function formatProducts($products): array
    {
        return collect($products)->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'image_url' => $p->image_url,
            'quantity' => $p->pivot->quantity ?? 1,
        ])->values()->all();
    }

    protected function buildFunnelProducts(?Lead $activeLead, $openDeals, ?int $readyForSalesStageId = null): array
    {
        return [
            'marketing' => $activeLead ? [
                'lead_id' => $activeLead->id,
                'stage' => $activeLead->marketingStage?->only(['id', 'name', 'color']),
                'is_ready_for_sales' => $readyForSalesStageId
                    ? $activeLead->marketing_stage_id === $readyForSalesStageId
                    : false,
                'products' => $this->formatProducts($activeLead->products ?? collect()),
            ] : null,
            'sales' => $openDeals->map(fn ($deal) => [
                'deal_id' => $deal->id,
                'title' => $deal->title,
                'stage' => $deal->stage?->only(['id', 'name', 'color']),
                'products' => $this->formatProducts($deal->products ?? collect()),
            ])->values()->all(),
        ];
    }

    protected function formatActivity(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'type' => $activity->type,
            'subject' => $activity->subject,
            'body' => $activity->body,
            'happened_at' => $activity->happened_at?->toIso8601String(),
            'scheduled_at' => $activity->scheduled_at?->toIso8601String(),
            'user' => $activity->user ? ['id' => $activity->user->id, 'name' => $activity->user->name] : null,
        ];
    }

    protected function formatTask(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'work_started_at' => $task->work_started_at?->toIso8601String(),
            'work_ended_at' => $task->work_ended_at?->toIso8601String(),
            'time_spent_minutes' => $task->time_spent_minutes,
            'assignee' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
        ];
    }

    protected function buildTimeline(
        Contact $contact,
        $leads,
        $deals,
        $activities,
        $tasks,
        $transitions,
    ): array {
        $events = collect();

        $events->push([
            'at' => $contact->created_at?->toIso8601String(),
            'type' => 'contact_created',
            'title' => 'ثبت مخاطب',
            'subtitle' => $contact->name,
            'icon' => 'tabler-user-plus',
            'color' => 'primary',
        ]);

        foreach ($leads as $lead) {
            $events->push([
                'at' => $lead->created_at?->toIso8601String(),
                'type' => 'lead_created',
                'title' => 'ثبت لید',
                'subtitle' => $lead->name,
                'icon' => 'tabler-target',
                'color' => 'info',
            ]);

            if ($lead->converted_at) {
                $events->push([
                    'at' => $lead->converted_at->toIso8601String(),
                    'type' => 'lead_converted',
                    'title' => 'تبدیل لید',
                    'subtitle' => $lead->name,
                    'icon' => 'tabler-arrow-right',
                    'color' => 'success',
                ]);
            }
        }

        foreach ($deals as $deal) {
            $events->push([
                'at' => $deal->created_at?->toIso8601String(),
                'type' => 'deal_created',
                'title' => 'معامله جدید',
                'subtitle' => $deal->title,
                'icon' => 'tabler-chart-funnel',
                'color' => 'success',
            ]);
        }

        foreach ($activities as $activity) {
            $at = $activity->scheduled_at ?? $activity->happened_at;

            $events->push([
                'at' => $at?->toIso8601String(),
                'type' => 'activity',
                'title' => match ($activity->type) {
                    'call' => 'تماس',
                    'meeting' => 'جلسه',
                    default => 'یادداشت',
                },
                'subtitle' => $activity->subject ?: '',
                'icon' => 'tabler-calendar-event',
                'color' => 'secondary',
            ]);
        }

        foreach ($tasks as $task) {
            if ($task->due_at) {
                $events->push([
                    'at' => $task->due_at->toIso8601String(),
                    'type' => 'task',
                    'title' => 'تسک',
                    'subtitle' => $task->title,
                    'icon' => 'tabler-checkbox',
                    'color' => 'warning',
                ]);
            }
        }

        foreach ($transitions as $t) {
            $events->push([
                'at' => $t->transitioned_at?->toIso8601String(),
                'type' => 'stage_change',
                'title' => 'تغییر مرحله',
                'subtitle' => ($t->fromStage?->name ?? '—').' → '.($t->toStage?->name ?? '—'),
                'icon' => 'tabler-arrows-exchange',
                'color' => 'info',
            ]);
        }

        return $events
            ->filter(fn ($e) => ! empty($e['at']))
            ->sortByDesc('at')
            ->values()
            ->take(60)
            ->all();
    }
}
