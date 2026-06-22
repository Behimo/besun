<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantCoreActive;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DealsKanbanSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_load_deals_kanban(): void
    {
        $this->withoutMiddleware(EnsureTenantCoreActive::class);

        $owner = User::factory()->create(['phone' => '09140000001']);

        $tenant = Tenant::create([
            'name' => 'Kanban Smoke Tenant',
            'slug' => 'kanban-smoke-tenant',
            'owner_id' => $owner->id,
            'status' => 'active',
        ]);

        $workspace = \App\Infrastructure\Persistence\Eloquent\Models\Workspace::create([
            'tenant_id' => $tenant->id,
            'name' => 'Default',
            'is_default' => true,
        ]);

        (new \Database\Seeders\TenantPermissionSeeder)->seedForTenant($tenant->id);

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
        $owner->syncRoles(['owner']);

        Sanctum::actingAs($owner->fresh());

        $this->getJson('/api/v1/deals?kanban=1')->assertOk();
    }
}
