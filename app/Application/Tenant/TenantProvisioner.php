<?php

namespace App\Application\Tenant;

use App\Domain\Shared\Enums\RoleName;
use App\Infrastructure\Persistence\Eloquent\Models\Plan;
use App\Infrastructure\Persistence\Eloquent\Models\PlanModule;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Subscription;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsAccount;
use App\Infrastructure\Persistence\Eloquent\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;
use App\Infrastructure\Services\TenantTeamService;
use Database\Seeders\TenantPermissionSeeder;
use Spatie\Permission\Models\Role;

class TenantProvisioner
{
    public const CORE_MODULE_SLUG = 'core-base';

    public function ensureCoreModuleCatalog(): PlanModule
    {
        $module = PlanModule::query()
            ->where('slug', self::CORE_MODULE_SLUG)
            ->where('is_active', true)
            ->first();

        if (! $module) {
            throw new RuntimeException('کاتالوگ ماژول seed نشده است. PlanSeeder را اجرا کنید.');
        }

        return $module;
    }

    public function formatCoreModuleSummary(?PlanModule $module = null): array
    {
        $module ??= $this->ensureCoreModuleCatalog();

        return [
            'id' => $module->id,
            'slug' => $module->slug,
            'name' => $module->name,
            'seat_monthly_price' => $module->seat_monthly_price ?? $module->monthly_price ?? $module->price,
            'features' => $module->features ?? [],
        ];
    }

    public function provision(User $user, string $name): Tenant
    {
        $this->ensureCoreModuleCatalog();

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'owner_id' => $user->id,
            'status' => 'active',
            'trial_ends_at' => null,
        ]);

        $plan = Plan::query()->where('is_active', true)->first()
            ?? Plan::query()->firstOrFail();

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'inactive',
        ]);

        TenantSmsAccount::firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['status' => TenantSmsAccount::STATUS_DRAFT],
        );

        $workspace = Workspace::create([
            'tenant_id' => $tenant->id,
            'name' => 'پیش‌فرض',
            'is_default' => true,
        ]);

        $tenant->users()->attach($user->id, [
            'joined_at' => now(),
        ]);
        $workspace->users()->attach($user->id);

        setPermissionsTeamId($tenant->id);
        (new TenantPermissionSeeder)->seedForTenant($tenant->id);
        app(TenantTeamService::class)->ensureSystemTeams($tenant->id);
        $user->assignRole(RoleName::Owner->value);

        $this->seedPipelineStages($tenant->id, $workspace->id);

        return $tenant->fresh(['subscription.plan']);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    protected function ensureRole(string $name): void
    {
        Role::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
            'tenant_id' => getPermissionsTeamId(),
        ]);
    }

    protected function seedPipelineStages(int $tenantId, int $workspaceId): void
    {
        foreach (config('crm_pipeline.sales_stages', config('crm_pipeline.default_stages', [])) as $stage) {
            PipelineStage::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'workspace_id' => $workspaceId,
                ...$stage,
            ]);
        }

        foreach (config('crm_pipeline.marketing_stages', []) as $stage) {
            PipelineStage::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'workspace_id' => $workspaceId,
                ...$stage,
            ]);
        }
    }
}
