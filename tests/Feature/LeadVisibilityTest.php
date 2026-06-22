<?php

namespace Tests\Feature;

use App\Domain\Shared\Enums\Department;
use App\Http\Middleware\EnsureTenantCoreActive;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\Workspace;
use App\Models\User;
use Database\Seeders\TenantPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Workspace $workspace;

    protected PipelineStage $marketingStage;

    protected PipelineStage $salesStage;

    protected User $owner;

    protected User $salesManager;

    protected User $marketingManager;

    protected User $marketingEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureTenantCoreActive::class);

        $this->owner = User::factory()->create(['phone' => '09130000001']);

        $this->tenant = Tenant::create([
            'name' => 'Lead Visibility Tenant',
            'slug' => 'lead-visibility-tenant',
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
        $this->salesManager = $this->makeMember('09130000002', 'sales_manager', Department::Sales->value);
        $this->marketingManager = $this->makeMember('09130000003', 'marketing_manager', Department::Marketing->value);
        $this->marketingEmployee = $this->makeMember('09130000004', 'marketing_employee', Department::Marketing->value);

        $this->marketingStage = PipelineStage::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'New Leads',
            'sort_order' => 1,
            'type' => 'marketing',
        ]);

        $this->salesStage = PipelineStage::create([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'name' => 'Sales Pipeline',
            'sort_order' => 1,
            'type' => 'sales',
        ]);
    }

    public function test_marketing_employee_sees_only_assigned_leads(): void
    {
        $own = $this->createLead(['assigned_to' => $this->marketingEmployee->id]);
        $unassigned = $this->createLead(['name' => 'Unassigned lead', 'assigned_to' => null]);
        $other = $this->createLead(['name' => 'Manager lead', 'assigned_to' => $this->marketingManager->id]);

        $this->actAs($this->marketingEmployee);
        $response = $this->getJson('/api/v1/leads');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($unassigned->id, $ids);
        $this->assertNotContains($other->id, $ids);

        $this->getJson("/api/v1/leads/{$unassigned->id}")->assertForbidden();
    }

    public function test_marketing_manager_sees_assigned_and_unassigned_leads_only(): void
    {
        $own = $this->createLead(['assigned_to' => $this->marketingManager->id]);
        $unassigned = $this->createLead(['name' => 'Queue lead', 'assigned_to' => null]);
        $employeeLead = $this->createLead(['name' => 'Employee lead', 'assigned_to' => $this->marketingEmployee->id]);

        $this->actAs($this->marketingManager);
        $response = $this->getJson('/api/v1/leads');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertContains($unassigned->id, $ids);
        $this->assertNotContains($employeeLead->id, $ids);
    }

    public function test_sales_manager_sees_unassigned_leads_but_not_assigned_leads(): void
    {
        $unassigned = $this->createLead(['assigned_to' => null]);
        $employeeLead = $this->createLead(['name' => 'Employee lead', 'assigned_to' => $this->marketingEmployee->id]);

        $this->actAs($this->salesManager);
        $response = $this->getJson('/api/v1/leads');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($unassigned->id, $ids);
        $this->assertNotContains($employeeLead->id, $ids);
    }

    public function test_marketing_manager_does_not_see_unassigned_sales_deals(): void
    {
        $this->grantOverride($this->marketingManager, ['deals.read']);
        $unassignedDeal = $this->createDeal(['assigned_to' => null]);

        $this->actAs($this->marketingManager);
        $response = $this->getJson('/api/v1/deals');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($unassignedDeal->id, $ids);
    }

    public function test_sales_manager_sees_unassigned_sales_deals(): void
    {
        $unassignedDeal = $this->createDeal(['assigned_to' => null]);
        $assignedDeal = $this->createDeal(['title' => 'Manager deal', 'assigned_to' => $this->salesManager->id]);

        $this->actAs($this->salesManager);
        $response = $this->getJson('/api/v1/deals');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($unassignedDeal->id, $ids);
        $this->assertContains($assignedDeal->id, $ids);
    }

    public function test_manual_lead_creation_assigns_creator(): void
    {
        $this->actAs($this->marketingEmployee);

        $response = $this->postJson('/api/v1/leads', [
            'name' => 'Created manually',
            'phone' => '09139990001',
            'marketing_stage_id' => $this->marketingStage->id,
        ]);

        $response->assertCreated();
        $this->assertSame($this->marketingEmployee->id, (int) $response->json('lead.assigned_to'));
    }

    public function test_lead_handoff_requires_assign_permission(): void
    {
        $lead = $this->createLead(['assigned_to' => $this->marketingEmployee->id]);

        $this->actAs($this->marketingEmployee);

        $this->postJson('/api/v1/handoffs', [
            'entity_type' => 'lead',
            'entity_id' => $lead->id,
            'to_user_id' => $this->salesManager->id,
            'handoff_type' => 'assign',
        ])->assertForbidden();
    }

    public function test_owner_sees_all_leads(): void
    {
        $assigned = $this->createLead(['assigned_to' => $this->marketingEmployee->id]);
        $unassigned = $this->createLead(['name' => 'Owner queue lead', 'assigned_to' => null]);

        $this->actAs($this->owner);
        $response = $this->getJson('/api/v1/leads');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($assigned->id, $ids);
        $this->assertContains($unassigned->id, $ids);
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

    protected function createLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'marketing_stage_id' => $this->marketingStage->id,
            'name' => 'Lead '.uniqid(),
            'phone' => '0913'.random_int(1000000, 9999999),
            'status' => 'new',
            'department' => Department::Marketing->value,
            'assigned_to' => $this->marketingEmployee->id,
        ], $overrides));
    }

    protected function createDeal(array $overrides = []): Deal
    {
        return Deal::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'workspace_id' => $this->workspace->id,
            'pipeline_stage_id' => $this->salesStage->id,
            'title' => 'Deal '.uniqid(),
            'amount' => 1000,
            'department' => Department::Sales->value,
            'assigned_to' => $this->salesManager->id,
        ], $overrides));
    }
}
