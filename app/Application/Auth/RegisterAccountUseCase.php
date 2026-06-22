<?php

namespace App\Application\Auth;

use App\Application\Tenant\TenantProvisioner;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterAccountUseCase
{
    public function __construct(
        protected TenantProvisioner $provisioner,
    ) {}

    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => PhoneNormalizer::normalize($data['phone']),
                'password' => Hash::make($data['password']),
            ]);

            $tenantName = $data['company'] ?? $data['name'];
            $tenant = $this->provisioner->provision($user, $tenantName);

            $user->update([
                'current_tenant_id' => null,
                'current_workspace_id' => null,
                'in_tenant_shell' => false,
            ]);

            return [
                'user' => $user->fresh(),
                'tenant' => $tenant,
                'open_purchase_for_tenant_id' => $tenant->id,
            ];
        });
    }
}
