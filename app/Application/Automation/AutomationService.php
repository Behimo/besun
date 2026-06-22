<?php

namespace App\Application\Automation;

use App\Infrastructure\Persistence\Eloquent\Models\AutomationRule;
use App\Infrastructure\Persistence\Eloquent\Models\AutomationRun;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AutomationService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected AutomationRuleMatcher $matcher,
        protected AutomationActionExecutor $executor,
    ) {}

    public function dashboard(): array
    {
        $activeRules = AutomationRule::where('is_active', true)->count();
        $totalRules = AutomationRule::count();

        $runsLast24h = AutomationRun::where('executed_at', '>=', now()->subDay())->count();
        $successLast24h = AutomationRun::where('executed_at', '>=', now()->subDay())
            ->where('status', AutomationRun::STATUS_SUCCESS)
            ->count();
        $failedLast24h = AutomationRun::where('executed_at', '>=', now()->subDay())
            ->where('status', AutomationRun::STATUS_FAILED)
            ->count();

        $recentErrors = AutomationRun::with('rule:id,name')
            ->where('status', AutomationRun::STATUS_FAILED)
            ->latest('executed_at')
            ->limit(5)
            ->get();

        return [
            'active_rules' => $activeRules,
            'total_rules' => $totalRules,
            'runs_last_24h' => $runsLast24h,
            'success_last_24h' => $successLast24h,
            'failed_last_24h' => $failedLast24h,
            'recent_errors' => $recentErrors,
        ];
    }

    public function meta(Tenant $tenant): array
    {
        $triggers = config('automation.triggers', []);
        $actions = config('automation.actions', []);
        $operators = config('automation.operators', []);
        $smsPlaceholders = config('automation.sms_placeholders', []);

        $users = $tenant->users()
            ->whereNull('tenant_user.left_at')
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        $stages = PipelineStage::query()
            ->select('id', 'name', 'type')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('type');

        return [
            'triggers' => $triggers,
            'condition_fields' => config('automation.condition_fields', []),
            'operators' => $operators,
            'actions' => $actions,
            'sms_placeholders' => $smsPlaceholders,
            'users' => $users,
            'stages' => $stages,
            'has_sms_module' => $tenant->hasModule('mod-sms'),
        ];
    }

    public function listRules(): \Illuminate\Database\Eloquent\Collection
    {
        return AutomationRule::query()
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRule(array $data): AutomationRule
    {
        $this->validateRulePayload($data);

        return AutomationRule::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'trigger_event' => $data['trigger_event'],
            'conditions' => $data['conditions'] ?? [],
            'actions' => $data['actions'],
            'is_active' => $data['is_active'] ?? true,
            'priority' => $data['priority'] ?? 100,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRule(AutomationRule $rule, array $data): AutomationRule
    {
        if (isset($data['trigger_event']) || isset($data['actions'])) {
            $this->validateRulePayload(array_merge($rule->toArray(), $data));
        }

        $rule->update([
            'name' => $data['name'] ?? $rule->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $rule->description,
            'trigger_event' => $data['trigger_event'] ?? $rule->trigger_event,
            'conditions' => $data['conditions'] ?? $rule->conditions,
            'actions' => $data['actions'] ?? $rule->actions,
            'is_active' => $data['is_active'] ?? $rule->is_active,
            'priority' => $data['priority'] ?? $rule->priority,
        ]);

        return $rule->fresh();
    }

    public function deleteRule(AutomationRule $rule): void
    {
        $rule->delete();
    }

    public function toggleRule(AutomationRule $rule): AutomationRule
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return $rule->fresh();
    }

    public function listRuns(int $page = 1, ?string $status = null): LengthAwarePaginator
    {
        $query = AutomationRun::with('rule:id,name')
            ->latest('executed_at');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate(20, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function processEvent(
        int $tenantId,
        ?int $workspaceId,
        string $event,
        string $entityType,
        int $entityId,
        array $context = [],
    ): void {
        $tenantContext = app(TenantContext::class);
        $tenantContext->set($tenantId, $workspaceId);

        try {
            $tenant = Tenant::findOrFail($tenantId);
            $entity = $this->resolveEntity($entityType, $entityId);

            if (! $entity) {
                return;
            }

            $actor = $this->resolveActor($context, $tenant);

            $rules = AutomationRule::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->where('trigger_event', $event)
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderBy('id')
                ->get();

            $context['automation_running'] = true;

            foreach ($rules as $rule) {
                $this->executeRule($rule, $entity, $tenant, $actor, $event, $context);
            }
        } finally {
            $tenantContext->clear();
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function executeRule(
        AutomationRule $rule,
        Model $entity,
        Tenant $tenant,
        User $actor,
        string $event,
        array $context,
    ): void {
        $conditions = $rule->conditions ?? [];

        if (! $this->matcher->matches($conditions, $entity, $context)) {
            $this->logRun($rule, $event, $entity, AutomationRun::STATUS_SKIPPED, ['reason' => 'conditions_not_met']);

            return;
        }

        try {
            $entity->refresh();
            $results = $this->executor->execute($rule->actions ?? [], $entity, $rule, $actor, $tenant, $context);

            $rule->update([
                'last_run_at' => now(),
                'run_count' => $rule->run_count + 1,
            ]);

            $this->logRun($rule, $event, $entity, AutomationRun::STATUS_SUCCESS, ['actions' => $results]);
        } catch (\Throwable $e) {
            $this->logRun($rule, $event, $entity, AutomationRun::STATUS_FAILED, null, $e->getMessage());
        }
    }

    protected function logRun(
        AutomationRule $rule,
        string $event,
        Model $entity,
        string $status,
        ?array $result = null,
        ?string $error = null,
    ): void {
        AutomationRun::create([
            'tenant_id' => $rule->tenant_id,
            'workspace_id' => $rule->workspace_id,
            'automation_rule_id' => $rule->id,
            'trigger_event' => $event,
            'entity_type' => $entity instanceof Lead ? 'lead' : ($entity instanceof Deal ? 'deal' : 'entity'),
            'entity_id' => $entity->id,
            'status' => $status,
            'result' => $result,
            'error_message' => $error,
            'executed_at' => now(),
        ]);
    }

    protected function resolveEntity(string $entityType, int $entityId): ?Model
    {
        return match ($entityType) {
            'lead' => Lead::withoutGlobalScopes()->with('contact')->find($entityId),
            'deal' => Deal::withoutGlobalScopes()->with('contact')->find($entityId),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveActor(array $context, Tenant $tenant): User
    {
        if (! empty($context['actor_id'])) {
            $user = User::find($context['actor_id']);
            if ($user) {
                return $user;
            }
        }

        if ($tenant->owner_id) {
            $owner = User::find($tenant->owner_id);
            if ($owner) {
                return $owner;
            }
        }

        throw new RuntimeException('کاربر اجراکننده یافت نشد.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateRulePayload(array $data): void
    {
        $triggers = config('automation.triggers', []);
        $actionsCatalog = config('automation.actions', []);

        if (empty($data['trigger_event']) || ! isset($triggers[$data['trigger_event']])) {
            throw new RuntimeException('رویداد تریگر نامعتبر است.');
        }

        $actionList = $data['actions'] ?? [];

        if (! is_array($actionList) || $actionList === []) {
            throw new RuntimeException('حداقل یک اقدام لازم است.');
        }

        foreach ($actionList as $action) {
            $type = $action['type'] ?? null;
            if (! $type || ! isset($actionsCatalog[$type])) {
                throw new RuntimeException('نوع اقدام نامعتبر است.');
            }
        }
    }
}
