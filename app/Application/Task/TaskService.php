<?php

namespace App\Application\Task;

use App\Infrastructure\Persistence\Eloquent\Models\Task;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\PermissionResolverService;
use App\Infrastructure\Services\TenantContext;
use App\Models\User;
use App\Notifications\CrmReminderNotification;
use App\Support\DepartmentAccessService;
use App\Support\NormalizesCrmRelatedType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class TaskService
{
    use NormalizesCrmRelatedType;

    public function __construct(
        protected TenantContext $tenantContext,
        protected PermissionResolverService $permissions,
        protected DepartmentAccessService $departments,
    ) {}

    public function isManager(User $user): bool
    {
        return $this->permissions->isManagerRole($user, $this->tenant()->id);
    }

    /** Can the user assign tasks to other members? */
    public function canAssign(User $user): bool
    {
        return $this->isOwner($user)
            || $this->permissions->hasPermission($user, $this->tenant()->id, 'tasks.assign');
    }

    /** Can the user see tasks of their department members? */
    public function canViewTeam(User $user): bool
    {
        return $this->isOwner($user)
            || $this->permissions->hasPermission($user, $this->tenant()->id, 'tasks.view_team');
    }

    public function canModify(User $user, Task $task): bool
    {
        return $this->taskInScope($user, $task);
    }

    public function canView(User $user, Task $task): bool
    {
        return $this->taskInScope($user, $task);
    }

    public function list(User $user, Request $request): LengthAwarePaginator
    {
        $query = Task::with(['assignee', 'creator', 'assigner']);
        $tenantId = $this->tenant()->id;

        $this->applyVisibilityScope($query, $user, $tenantId);

        if ($request->boolean('mine')) {
            $query->where('assignee_id', $user->id);
        } elseif ($request->boolean('assigned_by_me')) {
            $query->where('assigned_by', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->boolean('overdue')) {
            $query->where('status', '!=', 'completed')
                ->whereNotNull('due_at')
                ->where('due_at', '<', now());
        }

        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->integer('assignee_id'));
        }

        return $query
            ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', ['completed'])
            ->orderBy('due_at')
            ->paginate($request->integer('per_page', 15));
    }

    /** @return array<int, array{id: int, name: string}> */
    public function assignableUsers(User $user): array
    {
        $tenantId = $this->tenant()->id;

        if (! $this->canAssign($user)) {
            return [];
        }

        $query = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId)->whereNull('tenant_user.left_at'));

        if (! $this->isOwner($user)) {
            $department = $this->permissions->departmentFor($user, $tenantId);

            if (! $department) {
                return [];
            }

            $query->whereIn('id', $this->departments->departmentMemberIds($tenantId, $department));
        }

        return $query
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $member) => ['id' => $member->id, 'name' => $member->name])
            ->all();
    }

    public function create(User $user, array $data): Task
    {
        $tenant = $this->tenant();
        $canAssign = $this->canAssign($user);

        if ($canAssign && ! empty($data['assignee_id']) && (int) $data['assignee_id'] !== (int) $user->id) {
            $this->assertAssignableMember((int) $data['assignee_id'], $tenant->id, $user);
            $data['assigned_by'] = $user->id;
            $data['created_by'] = $user->id;
        } else {
            $data['assignee_id'] = $user->id;
            $data['created_by'] = $user->id;
            $data['assigned_by'] = null;
        }

        $data = $this->applyCompletionTimestamp($data, null);
        $data = $this->applyWorkTime($data, null);
        $data = $this->applyReminderReset($data, null);

        if (array_key_exists('related_type', $data)) {
            $data['related_type'] = $this->normalizeRelatedType($data['related_type'] ?? null);
        }

        return Task::create($data);
    }

    public function update(User $user, Task $task, array $data): Task
    {
        if (! $this->canModify($user, $task)) {
            abort(403, 'Unauthorized.');
        }

        $markingCompleted = ($data['status'] ?? null) === 'completed' && $task->status !== 'completed';

        if ($markingCompleted && (int) $task->assignee_id !== (int) $user->id) {
            abort(403, 'فقط مسئول انجام تسک می‌تواند آن را تکمیل کند.');
        }

        $canAssign = $this->canAssign($user);

        if ((int) $task->assignee_id !== (int) $user->id) {
            unset($data['completion_note']);
        }

        if (! $canAssign) {
            unset($data['assignee_id'], $data['assigned_by'], $data['effort_points']);
        } elseif (array_key_exists('assignee_id', $data) && $data['assignee_id'] !== null) {
            $this->assertAssignableMember((int) $data['assignee_id'], $this->tenant()->id, $user);

            if ((int) $data['assignee_id'] !== (int) $task->assignee_id) {
                $data['assigned_by'] = $user->id;
            }
        }

        $wasCompleted = $task->status === 'completed';

        $data = $this->applyCompletionTimestamp($data, $task);
        $data = $this->applyWorkTime($data, $task);
        $data = $this->applyReminderReset($data, $task);
        $task->update($data);

        $task = $task->fresh(['assignee', 'creator', 'assigner']);

        if (! $wasCompleted && $task->status === 'completed') {
            $this->notifyTaskCompleted($user, $task);
        }

        return $task;
    }

    public function delete(User $user, Task $task): void
    {
        if (! $this->canModify($user, $task)) {
            abort(403, 'Unauthorized.');
        }

        $task->delete();
    }

    protected function applyVisibilityScope($query, User $user, int $tenantId): void
    {
        if ($this->isOwner($user)) {
            return;
        }

        if ($this->permissions->hasPermission($user, $tenantId, 'tasks.view_team')) {
            $department = $this->permissions->departmentFor($user, $tenantId);
            $memberIds = $department ? $this->departments->departmentMemberIds($tenantId, $department) : [];

            $query->where(function ($q) use ($user, $memberIds) {
                $q->where('assignee_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhere('assigned_by', $user->id);

                if ($memberIds !== []) {
                    $q->orWhereIn('assignee_id', $memberIds);
                }
            });

            return;
        }

        $query->where(function ($q) use ($user) {
            $q->where('assignee_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhere('assigned_by', $user->id);
        });
    }

    protected function taskInScope(User $user, Task $task): bool
    {
        $tenantId = $this->tenant()->id;

        if ((int) $task->assignee_id === (int) $user->id
            || (int) $task->created_by === (int) $user->id
            || (int) $task->assigned_by === (int) $user->id) {
            return true;
        }

        if ($this->isOwner($user)) {
            return true;
        }

        if ($this->permissions->hasPermission($user, $tenantId, 'tasks.view_team')) {
            $department = $this->permissions->departmentFor($user, $tenantId);

            if ($department) {
                return in_array((int) $task->assignee_id, $this->departments->departmentMemberIds($tenantId, $department), true);
            }
        }

        return false;
    }

    protected function isOwner(User $user): bool
    {
        return $this->permissions->isOwnerRole($user, $this->tenant()->id);
    }

    protected function applyCompletionTimestamp(array $data, ?Task $existing): array
    {
        $newStatus = $data['status'] ?? $existing?->status;

        if ($newStatus === 'completed') {
            $data['completed_at'] = $data['completed_at'] ?? now();
        } elseif (array_key_exists('status', $data) && $data['status'] !== 'completed') {
            $data['completed_at'] = null;
            $data['completion_note'] = null;
        }

        return $data;
    }

    protected function applyWorkTime(array $data, ?Task $existing): array
    {
        $newStatus = $data['status'] ?? $existing?->status;

        if ($newStatus !== 'completed') {
            if (array_key_exists('status', $data) && $data['status'] !== 'completed') {
                $data['work_started_at'] = null;
                $data['work_ended_at'] = null;
                $data['time_spent_minutes'] = null;
            }

            return $data;
        }

        $startedAt = $data['work_started_at'] ?? $existing?->work_started_at;
        $endedAt = $data['work_ended_at'] ?? $existing?->work_ended_at;

        if ($startedAt && $endedAt) {
            $start = \Carbon\Carbon::parse($startedAt);
            $end = \Carbon\Carbon::parse($endedAt);

            if ($end->lessThanOrEqualTo($start)) {
                abort(422, 'ساعت پایان باید بعد از ساعت شروع باشد.');
            }

            $data['time_spent_minutes'] = $data['time_spent_minutes']
                ?? max(1, (int) $start->diffInMinutes($end));
        }

        return $data;
    }

    protected function applyReminderReset(array $data, ?Task $existing): array
    {
        if (! array_key_exists('reminder_at', $data)) {
            return $data;
        }

        $newReminder = $data['reminder_at']
            ? \Carbon\Carbon::parse($data['reminder_at'])->timestamp
            : null;

        $oldReminder = $existing?->reminder_at?->timestamp;

        if ($newReminder !== $oldReminder) {
            $data['reminder_sent_at'] = null;
        }

        return $data;
    }

    protected function notifyTaskCompleted(User $completer, Task $task): void
    {
        $recipientId = $task->assigned_by ?? $task->created_by;

        if (! $recipientId || (int) $recipientId === (int) $completer->id) {
            return;
        }

        $recipient = User::find($recipientId);

        if (! $recipient) {
            return;
        }

        $assigneeName = $task->assignee?->name ?? $completer->name;

        $recipient->notify(new CrmReminderNotification(
            title: 'تسک تکمیل شد',
            subtitle: "{$task->title} — توسط {$assigneeName}",
            url: '/apps/crm/tasks',
            entityType: 'task',
            entityId: $task->id,
            tenantId: $task->tenant_id,
            color: 'success',
            icon: 'tabler-circle-check',
        ));
    }

    protected function assertAssignableMember(int $userId, int $tenantId, User $actor): void
    {
        if ($this->isOwner($actor)) {
            $this->assertTenantMember($userId, $tenantId);

            return;
        }

        $department = $this->permissions->departmentFor($actor, $tenantId);

        if (! $department) {
            abort(403, 'دسترسی واگذاری تسک ندارید.');
        }

        if (! in_array($userId, $this->departments->departmentMemberIds($tenantId, $department), true)) {
            abort(422, 'فقط می‌توانید تسک را به اعضای واحد خود واگذار کنید.');
        }
    }

    protected function assertTenantMember(int $userId, int $tenantId): void
    {
        $exists = User::where('id', $userId)
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->exists();

        if (! $exists) {
            abort(422, 'کاربر انتخاب‌شده عضو این مجموعه نیست.');
        }
    }

    protected function tenant(): Tenant
    {
        return Tenant::findOrFail($this->tenantContext->tenantId());
    }
}
