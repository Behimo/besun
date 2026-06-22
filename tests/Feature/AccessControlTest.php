<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantCoreActive;
use App\Infrastructure\Persistence\Eloquent\Models\Activity;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\Workspace;
use App\Models\User;
use Database\Seeders\TenantPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Workspace $workspace;

    protected User $owner;

    protected User $salesManager;

    protected User $salesEmployeeA;

    protected User $salesEmployeeB;

    protected User $marketingEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureTenantCoreActive::class);

        $this->owner = User::factory()->create(['phone' => '09120000001']);

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'owner_id' => $this->owner->id,
            'status' => 'active',
        ]);

        $this->workspace = Workspace::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        (new TenantPermissionSeeder)->seedForTenant($this->tenant->id);

        $this->attachMember($this->owner, 'owner', null);

        $this->salesManager = $this->makeMember('09120000002', 'sales_manager', 'sales');
        $this->salesEmployeeA = $this->makeMember('09120000003', 'sales_employee', 'sales');
        $this->salesEmployeeB = $this->makeMember('09120000004', 'sales_employee', 'sales');
        $this->marketingEmployee = $this->makeMember('09120000005', 'marketing_employee', 'marketing');
    }

    protected function makeMember(string $phone, string $role, ?string $department): User
    {
        $user = User::factory()->create(['phone' => $phone]);
        $this->attachMember($user, $role, $department);

        return $user;
    }

    protected function attachMember(User $user, string $role, ?string $department): void
    {
        $user->tenants()->attach($this->tenant->id, [
            'joined_at' => now(),
            'department' => $department,
            'permission_overrides' => json_encode(['grant' => [], 'revoke' => []]),
        ]);

        $user->workspaces()->attach($this->workspace->id);

        $user->forceFill([
            'current_tenant_id' => $this->tenant->id,
            'current_workspace_id' => $this->workspace->id,
            'in_tenant_shell' => true,
        ])->save();

        setPermissionsTeamId($this->tenant->id);
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $user->syncRoles([$role]);
    }

    protected function actAs(User $user): void
    {
        // Permission relations are cached per-request; reset between actors.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user->unsetRelation('roles');
        Sanctum::actingAs($user->fresh());
    }

    protected function grantOverride(User $user, array $grant, array $revoke = []): void
    {
        DB::table('tenant_user')
            ->where('tenant_id', $this->tenant->id)
            ->where('user_id', $user->id)
            ->update(['permission_overrides' => json_encode(['grant' => $grant, 'revoke' => $revoke])]);
    }

    protected function createReport(User $user, string $date): int
    {
        $this->actAs($user);

        $response = $this->postJson('/api/v1/daily-work-reports', [
            'report_date' => $date,
            'summary' => 'گزارش تست',
            'entries' => [
                ['title' => 'کار تست', 'minutes' => 60, 'effort_score' => 3],
            ],
        ]);

        $response->assertCreated();

        return (int) $response->json('report.id');
    }

    protected function createTask(User $user, array $payload = []): array
    {
        $this->actAs($user);

        $response = $this->postJson('/api/v1/tasks', array_merge([
            'title' => 'تسک تست',
        ], $payload));

        $response->assertCreated();

        return $response->json('task');
    }

    // ----- Daily work reports -----

    public function test_sales_employee_sees_only_own_reports(): void
    {
        $ownId = $this->createReport($this->salesEmployeeA, '2026-06-01');
        $otherId = $this->createReport($this->salesEmployeeB, '2026-06-01');

        $this->actAs($this->salesEmployeeA);
        $response = $this->getJson('/api/v1/daily-work-reports');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ownId, $ids);
        $this->assertNotContains($otherId, $ids);

        $this->getJson("/api/v1/daily-work-reports/{$otherId}")->assertForbidden();
    }

    public function test_sales_manager_sees_only_department_reports(): void
    {
        $salesReportId = $this->createReport($this->salesEmployeeA, '2026-06-01');
        $marketingReportId = $this->createReport($this->marketingEmployee, '2026-06-01');

        $this->actAs($this->salesManager);
        $response = $this->getJson('/api/v1/daily-work-reports');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($salesReportId, $ids);
        $this->assertNotContains($marketingReportId, $ids);

        $this->getJson("/api/v1/daily-work-reports/{$marketingReportId}")->assertForbidden();
    }

    public function test_owner_sees_all_reports(): void
    {
        $salesReportId = $this->createReport($this->salesEmployeeA, '2026-06-01');
        $marketingReportId = $this->createReport($this->marketingEmployee, '2026-06-01');

        $this->actAs($this->owner);
        $response = $this->getJson('/api/v1/daily-work-reports');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($salesReportId, $ids);
        $this->assertContains($marketingReportId, $ids);
    }

    public function test_employee_cannot_review_reports(): void
    {
        $reportId = $this->createReport($this->salesEmployeeA, '2026-06-01');

        $this->actAs($this->salesEmployeeA);
        $this->postJson("/api/v1/daily-work-reports/{$reportId}/submit")->assertOk();

        $this->actAs($this->salesEmployeeB);
        $this->postJson("/api/v1/daily-work-reports/{$reportId}/review", [
            'manager_score' => 5,
        ])->assertForbidden();
    }

    public function test_manager_can_review_department_report(): void
    {
        $reportId = $this->createReport($this->salesEmployeeA, '2026-06-01');

        $this->actAs($this->salesEmployeeA);
        $this->postJson("/api/v1/daily-work-reports/{$reportId}/submit")->assertOk();

        $this->actAs($this->salesManager);
        $this->postJson("/api/v1/daily-work-reports/{$reportId}/review", [
            'manager_score' => 4,
            'manager_feedback' => 'خوب',
        ])->assertOk();
    }

    public function test_performance_endpoint_forbidden_for_employee(): void
    {
        $this->actAs($this->salesEmployeeA);
        $this->getJson('/api/v1/daily-work-reports/performance')->assertForbidden();

        $this->actAs($this->salesManager);
        $this->getJson('/api/v1/daily-work-reports/performance')->assertOk();
    }

    // ----- Tasks -----

    public function test_employee_cannot_assign_tasks_to_others(): void
    {
        $task = $this->createTask($this->salesEmployeeA, [
            'assignee_id' => $this->salesEmployeeB->id,
        ]);

        // Without tasks.assign the assignee is forced back to the creator.
        $this->assertSame($this->salesEmployeeA->id, (int) $task['assignee_id']);
    }

    public function test_employee_with_assign_permission_can_assign_within_department(): void
    {
        $this->grantOverride($this->salesEmployeeA, ['tasks.assign']);

        $task = $this->createTask($this->salesEmployeeA, [
            'assignee_id' => $this->salesEmployeeB->id,
        ]);

        $this->assertSame($this->salesEmployeeB->id, (int) $task['assignee_id']);
    }

    public function test_manager_cannot_assign_task_outside_department(): void
    {
        $this->actAs($this->salesManager);

        $this->postJson('/api/v1/tasks', [
            'title' => 'تسک خارج از واحد',
            'assignee_id' => $this->marketingEmployee->id,
        ])->assertStatus(422);
    }

    public function test_owner_can_assign_task_to_anyone(): void
    {
        $task = $this->createTask($this->owner, [
            'assignee_id' => $this->marketingEmployee->id,
        ]);

        $this->assertSame($this->marketingEmployee->id, (int) $task['assignee_id']);
    }

    public function test_employee_sees_only_own_tasks(): void
    {
        $ownTask = $this->createTask($this->salesEmployeeA);
        $otherTask = $this->createTask($this->salesEmployeeB);

        $this->actAs($this->salesEmployeeA);
        $response = $this->getJson('/api/v1/tasks');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ownTask['id'], $ids);
        $this->assertNotContains($otherTask['id'], $ids);
    }

    public function test_manager_sees_department_tasks_only(): void
    {
        $salesTask = $this->createTask($this->salesEmployeeA);
        $marketingTask = $this->createTask($this->marketingEmployee);

        $this->actAs($this->salesManager);
        $response = $this->getJson('/api/v1/tasks');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($salesTask['id'], $ids);
        $this->assertNotContains($marketingTask['id'], $ids);
    }

    public function test_assignees_endpoint_empty_without_assign_permission(): void
    {
        $this->actAs($this->salesEmployeeA);
        $response = $this->getJson('/api/v1/tasks/assignees');
        $response->assertOk();
        $this->assertSame([], $response->json('users'));

        $this->actAs($this->salesManager);
        $response = $this->getJson('/api/v1/tasks/assignees');
        $response->assertOk();
        $this->assertNotEmpty($response->json('users'));
    }

    // ----- Custom roles -----

    public function test_owner_can_create_custom_role(): void
    {
        $this->actAs($this->owner);

        $response = $this->postJson('/api/v1/tenant/access/roles', [
            'label' => 'سرپرست فروش',
            'department' => 'sales',
            'is_manager' => true,
            'permissions' => ['tasks.read', 'tasks.assign', 'daily_reports.read', 'daily_reports.view_team', 'daily_reports.review'],
        ]);

        $response->assertCreated();
        $this->assertTrue($response->json('role.is_custom'));
        $this->assertTrue($response->json('role.is_manager'));
        $this->assertContains('tasks.assign', $response->json('role.permissions'));
    }

    public function test_non_owner_cannot_manage_roles(): void
    {
        $this->actAs($this->salesManager);

        $this->postJson('/api/v1/tenant/access/roles', [
            'label' => 'نقش غیرمجاز',
        ])->assertForbidden();

        $this->getJson('/api/v1/tenant/access/roles')->assertForbidden();
    }

    public function test_custom_manager_role_sees_team_reports(): void
    {
        $this->actAs($this->owner);

        $created = $this->postJson('/api/v1/tenant/access/roles', [
            'label' => 'سرپرست فروش',
            'department' => 'sales',
            'is_manager' => true,
            'permissions' => ['daily_reports.read', 'daily_reports.view_team', 'daily_reports.review'],
        ]);
        $created->assertCreated();
        $roleName = $created->json('role.name');

        $supervisor = $this->makeMember('09120000006', $roleName, 'sales');

        $reportId = $this->createReport($this->salesEmployeeA, '2026-06-01');
        $marketingReportId = $this->createReport($this->marketingEmployee, '2026-06-01');

        $this->actAs($supervisor);
        $response = $this->getJson('/api/v1/daily-work-reports');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($reportId, $ids);
        $this->assertNotContains($marketingReportId, $ids);
    }

    public function test_owner_can_assign_custom_role_to_member(): void
    {
        $this->actAs($this->owner);

        $created = $this->postJson('/api/v1/tenant/access/roles', [
            'label' => 'حسابدار ویژه',
            'department' => 'finance',
            'permissions' => ['invoicing.read', 'tasks.read'],
        ]);
        $created->assertCreated();
        $roleName = $created->json('role.name');

        $response = $this->putJson("/api/v1/users/{$this->salesEmployeeB->id}/access", [
            'role' => $roleName,
            'department' => 'finance',
        ]);

        $response->assertOk();
        $this->assertSame($roleName, $response->json('user.role'));
        $this->assertContains('invoicing.read', $response->json('user.permissions'));
        $this->assertNotContains('daily_reports.view_team', $response->json('user.permissions'));
    }

    public function test_custom_role_with_members_cannot_be_deleted(): void
    {
        $this->actAs($this->owner);

        $created = $this->postJson('/api/v1/tenant/access/roles', [
            'label' => 'نقش موقت',
            'permissions' => ['tasks.read'],
        ]);
        $roleName = $created->json('role.name');

        $member = $this->makeMember('09120000007', $roleName, null);

        $this->actAs($this->owner);
        $this->deleteJson("/api/v1/tenant/access/roles/{$roleName}")->assertStatus(422);

        // After moving the member off the role it becomes deletable.
        setPermissionsTeamId($this->tenant->id);
        $member->syncRoles(['sales_employee']);

        $this->deleteJson("/api/v1/tenant/access/roles/{$roleName}")->assertOk();
    }

    // ----- Sales reports (HR scope) -----

    public function test_hr_metrics_scoped_to_manager_department(): void
    {
        $this->createTask($this->salesEmployeeA);
        $this->createTask($this->marketingEmployee);

        $this->actAs($this->salesManager);
        $response = $this->getJson('/api/v1/reports');
        $response->assertOk();

        $taskUserIds = collect($response->json('task_performance'))->pluck('user_id')->all();
        $this->assertContains($this->salesEmployeeA->id, $taskUserIds);
        $this->assertNotContains($this->marketingEmployee->id, $taskUserIds);

        $this->actAs($this->owner);
        $response = $this->getJson('/api/v1/reports');
        $response->assertOk();

        $taskUserIds = collect($response->json('task_performance'))->pluck('user_id')->all();
        $this->assertContains($this->salesEmployeeA->id, $taskUserIds);
        $this->assertContains($this->marketingEmployee->id, $taskUserIds);
    }

    public function test_employee_cannot_access_sales_reports(): void
    {
        $this->actAs($this->salesEmployeeA);
        $this->getJson('/api/v1/reports')->assertForbidden();
    }

    // ----- Activities -----

    public function test_activity_delete_requires_ownership(): void
    {
        // Both get the delete permission; ownership must still be enforced.
        $this->grantOverride($this->salesEmployeeA, ['activities.delete']);
        $this->grantOverride($this->salesEmployeeB, ['activities.delete']);

        $this->actAs($this->salesEmployeeA);
        $created = $this->postJson('/api/v1/activities', [
            'type' => 'note',
            'subject' => 'یادداشت تست',
        ]);
        $created->assertCreated();
        $activityId = $created->json('activity.id');

        $this->actAs($this->salesEmployeeB);
        $this->deleteJson("/api/v1/activities/{$activityId}")->assertForbidden();

        $this->actAs($this->salesEmployeeA);
        $this->deleteJson("/api/v1/activities/{$activityId}")->assertOk();
    }

    public function test_owner_can_delete_any_activity(): void
    {
        $this->actAs($this->salesEmployeeA);
        $created = $this->postJson('/api/v1/activities', [
            'type' => 'note',
            'subject' => 'یادداشت تست',
        ]);
        $activityId = $created->json('activity.id');

        $this->actAs($this->owner);
        $this->deleteJson("/api/v1/activities/{$activityId}")->assertOk();
    }

    // ----- Revoked permissions -----

    public function test_revoked_tasks_read_blocks_task_list(): void
    {
        $this->grantOverride($this->salesEmployeeA, [], ['tasks.read']);

        $this->actAs($this->salesEmployeeA);
        $this->getJson('/api/v1/tasks')->assertForbidden();
    }

    public function test_revoked_daily_reports_read_blocks_report_list(): void
    {
        $this->grantOverride($this->salesEmployeeA, [], ['daily_reports.read']);

        $this->actAs($this->salesEmployeeA);
        $this->getJson('/api/v1/daily-work-reports')->assertForbidden();
    }
}
