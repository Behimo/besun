<?php

namespace App\Application\Tenant;

use App\Domain\Shared\Enums\RoleName;
use App\Infrastructure\Persistence\Eloquent\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreateWorkspaceUseCase
{
    public function execute(User $user, int $tenantId, string $name): Workspace
    {
        if (! $user->belongsToTenant($tenantId)) {
            abort(403, 'Access denied to tenant.');
        }

        return DB::transaction(function () use ($user, $tenantId, $name) {
            $workspace = Workspace::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'is_default' => false,
            ]);

            $workspace->users()->attach($user->id);

            setPermissionsTeamId($tenantId);
            $this->ensureRole(RoleName::SalesManager->value);

            return $workspace;
        });
    }

    protected function ensureRole(string $name): void
    {
        Role::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
            'tenant_id' => getPermissionsTeamId(),
        ]);
    }
}
