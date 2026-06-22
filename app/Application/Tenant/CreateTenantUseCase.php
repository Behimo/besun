<?php

namespace App\Application\Tenant;

use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTenantUseCase
{
    public function __construct(
        protected TenantProvisioner $provisioner,
    ) {}

    public function execute(User $user, string $name): Tenant
    {
        return DB::transaction(function () use ($user, $name) {
            $tenant = $this->provisioner->provision($user, $name);

            $user->update([
                'current_tenant_id' => null,
                'current_workspace_id' => null,
                'in_tenant_shell' => false,
            ]);

            return $tenant;
        });
    }
}
