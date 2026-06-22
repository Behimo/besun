<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantCoreActive;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\Workspace;
use App\Models\User;
use Database\Seeders\TenantPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketingKanbanSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_load_marketing_kanban(): void
    {
        $this->withoutMiddleware(EnsureTenantCoreActive::class);

        $owner = User::factory()->create(['phone' => '09150000001']);

        $tenant = Tenant::create([
            'name' => 'Marketing Kanban Tenant',
            'slug' => 'marketing-kanban-tenant',
            'owner_id' => $owner->id,
            'status' => 'active',
        ]);

        $workspace = Workspace::create([
            'tenant_id' => $tenant->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        (new TenantPermissionSeeder)->seedForTenant($tenant->id);

        PipelineStage::create([
            'tenant_id' => $tenant->id,
            'workspace_id' => $workspace->id,
            'name' => 'New',
            'sort_order' => 1,
            'type' => 'marketing',
        ]);

        $owner->tenants()->attach($tenant->id, [
            'joined_at' => now(),
            'permission_overrides' => json_encode(['grant' => [], 'revoke' => []]),
        ]);
        $owner->workspaces()->attach($workspace->id);
        $owner->forceFill([
            'current_tenant_id' => $tenant->id,
            'current_workspace_id' => $workspace->id,
            'in_tenant_shell' => true,
        ])->save();

        setPermissionsTeamId($tenant->id);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $owner->syncRoles(['owner']);

        Sanctum::actingAs($owner->fresh());

        $this->getJson('/api/v1/leads?kanban=1')->assertOk();
    }
}
