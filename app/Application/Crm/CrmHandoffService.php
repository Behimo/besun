<?php

namespace App\Application\Crm;

use App\Application\Pipeline\PipelineTransitionLogger;
use App\Application\Task\TaskService;
use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Contact;
use App\Infrastructure\Persistence\Eloquent\Models\CrmHandoff;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Services\TenantContext;
use App\Models\User;
use App\Notifications\CrmReminderNotification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CrmHandoffService
{
    public function __construct(
        protected PipelineTransitionLogger $transitions,
        protected TaskService $tasks,
        protected TenantContext $tenantContext,
    ) {}

    public function assign(User $actor, string $entityType, int $entityId, array $data): CrmHandoff
    {
        return DB::transaction(function () use ($actor, $entityType, $entityId, $data) {
            [$entity, $stageField] = $this->resolveEntity($entityType, $entityId);
            $toUser = $this->resolveTenantUser((int) $data['to_user_id']);

            $fromStageId = $entity->{$stageField};
            $toStageId = $data['to_stage_id'] ?? $fromStageId;
            $handoffType = $data['handoff_type'] ?? 'assign';

            if ($handoffType === 'finance' && empty($data['to_stage_id'])) {
                $toStageId = $this->financeStageId($entity->tenant_id) ?? $toStageId;
            }

            $updates = ['assigned_to' => $toUser->id];

            if ($toStageId && $toStageId !== $fromStageId) {
                $updates[$stageField] = $toStageId;
            }

            $entity->update($updates);

            if ($toStageId && $toStageId !== $fromStageId) {
                $this->transitions->log($entityType, $entity->id, $fromStageId, $toStageId, $actor->id);
            }

            $taskId = null;

            if (! empty($data['create_task'])) {
                $relatedClass = $entityType === 'deal' ? Deal::class : Lead::class;
                $task = $this->tasks->create($actor, [
                    'title' => $data['task_title'] ?? $this->defaultTaskTitle($entityType, $entity),
                    'description' => $data['note'] ?? null,
                    'assignee_id' => $toUser->id,
                    'due_at' => $data['task_due_at'] ?? null,
                    'related_type' => $relatedClass,
                    'related_id' => $entity->id,
                ]);
                $taskId = $task->id;
            }

            $handoff = CrmHandoff::create([
                'entity_type' => $entityType,
                'entity_id' => $entity->id,
                'from_user_id' => $actor->id,
                'to_user_id' => $toUser->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $toStageId,
                'handoff_type' => $handoffType,
                'note' => $data['note'] ?? null,
                'status' => 'pending',
                'task_id' => $taskId,
            ]);

            $this->logActivity($entity, $actor, $handoff);
            $this->notifyAssignee($toUser, $entityType, $entity, $handoff);

            return $handoff->load(['fromUser:id,name', 'toUser:id,name', 'fromStage', 'toStage', 'task']);
        });
    }

    public function returnToSender(User $actor, CrmHandoff $handoff, array $data): CrmHandoff
    {
        if ($handoff->status !== 'pending') {
            throw new InvalidArgumentException('این واگذاری قبلاً بسته شده است.');
        }

        if ((int) $handoff->to_user_id !== (int) $actor->id) {
            abort(403, 'فقط گیرنده می‌تواند پرونده را بازگرداند.');
        }

        return DB::transaction(function () use ($actor, $handoff, $data) {
            [$entity, $stageField] = $this->resolveEntity($handoff->entity_type, $handoff->entity_id);

            $returnUserId = (int) ($data['return_to_user_id'] ?? $handoff->from_user_id);
            $returnUser = $this->resolveTenantUser($returnUserId);

            $fromStageId = $entity->{$stageField};
            $toStageId = $data['to_stage_id'] ?? $handoff->from_stage_id ?? $fromStageId;

            $entity->update([
                'assigned_to' => $returnUser->id,
                $stageField => $toStageId,
            ]);

            if ($toStageId && $toStageId !== $fromStageId) {
                $this->transitions->log($handoff->entity_type, $entity->id, $fromStageId, $toStageId, $actor->id);
            }

            $handoff->update([
                'status' => 'returned',
                'returned_to_user_id' => $returnUser->id,
                'resolved_at' => now(),
            ]);

            $returnHandoff = CrmHandoff::create([
                'entity_type' => $handoff->entity_type,
                'entity_id' => $handoff->entity_id,
                'from_user_id' => $actor->id,
                'to_user_id' => $returnUser->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $toStageId,
                'handoff_type' => 'return',
                'note' => $data['note'] ?? 'بازگرداندن از '.($handoff->handoff_type === 'finance' ? 'مالی' : 'همکار'),
                'status' => 'pending',
                'parent_handoff_id' => $handoff->id,
            ]);

            $this->logActivity($entity, $actor, $returnHandoff, true);
            $this->notifyAssignee($returnUser, $handoff->entity_type, $entity, $returnHandoff, true);

            return $returnHandoff->load(['fromUser:id,name', 'toUser:id,name', 'fromStage', 'toStage', 'parentHandoff']);
        });
    }

    public function complete(User $actor, CrmHandoff $handoff, ?string $note = null): CrmHandoff
    {
        if ($handoff->status !== 'pending') {
            throw new InvalidArgumentException('این واگذاری قبلاً بسته شده است.');
        }

        if ((int) $handoff->to_user_id !== (int) $actor->id) {
            abort(403, 'فقط گیرنده می‌تواند واگذاری را تکمیل کند.');
        }

        $handoff->update([
            'status' => 'completed',
            'note' => $note ? trim($handoff->note."\n".$note) : $handoff->note,
            'resolved_at' => now(),
        ]);

        return $handoff->fresh(['fromUser:id,name', 'toUser:id,name', 'fromStage', 'toStage']);
    }

    public function pendingForUser(User $user, ?int $contactId = null): array
    {
        $query = CrmHandoff::query()
            ->with(['fromUser:id,name', 'toUser:id,name', 'fromStage', 'toStage'])
            ->where('status', 'pending')
            ->where('to_user_id', $user->id)
            ->latest();

        if ($contactId) {
            $leadIds = Lead::query()->where('contact_id', $contactId)->pluck('id');
            $dealIds = Deal::query()->where('contact_id', $contactId)->pluck('id');

            if ($leadIds->isEmpty() && $dealIds->isEmpty()) {
                return [];
            }

            $query->where(function ($q) use ($leadIds, $dealIds) {
                if ($leadIds->isNotEmpty()) {
                    $q->where(function ($inner) use ($leadIds) {
                        $inner->where('entity_type', 'lead')->whereIn('entity_id', $leadIds);
                    });
                }

                if ($dealIds->isNotEmpty()) {
                    $method = $leadIds->isNotEmpty() ? 'orWhere' : 'where';
                    $q->{$method}(function ($inner) use ($dealIds) {
                        $inner->where('entity_type', 'deal')->whereIn('entity_id', $dealIds);
                    });
                }
            });
        }

        return $query->limit(20)->get()->map(fn (CrmHandoff $h) => $this->formatHandoff($h))->all();
    }

    public function formatHandoff(CrmHandoff $handoff): array
    {
        return [
            'id' => $handoff->id,
            'entity_type' => $handoff->entity_type,
            'entity_id' => $handoff->entity_id,
            'handoff_type' => $handoff->handoff_type,
            'status' => $handoff->status,
            'note' => $handoff->note,
            'from_user' => $handoff->fromUser?->only(['id', 'name']),
            'to_user' => $handoff->toUser?->only(['id', 'name']),
            'from_stage' => $handoff->fromStage?->only(['id', 'name', 'color']),
            'to_stage' => $handoff->toStage?->only(['id', 'name', 'color']),
            'created_at' => $handoff->created_at?->toIso8601String(),
        ];
    }

    protected function resolveEntity(string $entityType, int $entityId): array
    {
        return match ($entityType) {
            'deal' => [Deal::findOrFail($entityId), 'pipeline_stage_id'],
            'lead' => [Lead::findOrFail($entityId), 'marketing_stage_id'],
            default => throw new InvalidArgumentException('نوع موجودیت نامعتبر است.'),
        };
    }

    protected function resolveTenantUser(int $userId): User
    {
        $tenantId = $this->tenantContext->tenantId();

        $user = User::query()
            ->where('id', $userId)
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->first();

        if (! $user) {
            abort(422, 'کاربر انتخاب‌شده عضو این مجموعه نیست.');
        }

        return $user;
    }

    protected function financeStageId(int $tenantId): ?int
    {
        return PipelineStage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'sales')
            ->where('name', 'مالی')
            ->value('id');
    }

    protected function defaultTaskTitle(string $entityType, Deal|Lead $entity): string
    {
        if ($entityType === 'deal') {
            return 'پیگیری معامله: '.$entity->title;
        }

        return 'پیگیری لید: '.$entity->name;
    }

    protected function logActivity(Deal|Lead $entity, User $actor, CrmHandoff $handoff, bool $isReturn = false): void
    {
        $contactId = $entity->contact_id ?? null;
        $relatedType = $contactId ? Contact::class : ($entity instanceof Deal ? Deal::class : Lead::class);
        $relatedId = $contactId ?? $entity->id;

        $typeLabel = match ($handoff->handoff_type) {
            'finance' => 'ارسال به مالی',
            'return' => 'بازگشت از واگذاری',
            default => 'واگذاری',
        };

        Activity::create([
            'type' => 'note',
            'subject' => $isReturn ? 'بازگرداندن پرونده' : $typeLabel,
            'body' => trim(($handoff->note ?? '')."\nمسئول جدید: ".($handoff->toUser?->name ?? '')),
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'user_id' => $actor->id,
            'happened_at' => now(),
        ]);
    }

    protected function notifyAssignee(User $toUser, string $entityType, Deal|Lead $entity, CrmHandoff $handoff, bool $isReturn = false): void
    {
        $contactId = $entity->contact_id;
        $url = $contactId ? '/apps/crm/contacts/'.$contactId : '/apps/crm/'.($entityType === 'deal' ? 'deals' : 'leads');

        $title = $isReturn
            ? 'بازگشت پرونده برای پیگیری'
            : match ($handoff->handoff_type) {
                'finance' => 'ارجاع به مالی',
                default => 'واگذاری جدید',
            };

        $label = $entity instanceof Deal ? $entity->title : $entity->name;

        $toUser->notify(new CrmReminderNotification(
            title: $title,
            subtitle: $label,
            url: $url,
            entityType: $entityType,
            entityId: $entity->id,
            tenantId: $entity->tenant_id,
            color: $handoff->handoff_type === 'finance' ? 'secondary' : 'info',
            icon: 'tabler-user-share',
        ));
    }
}
