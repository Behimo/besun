<?php

namespace App\Application\Sms;

use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsAccount;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsPanelRequest;
use App\Infrastructure\Services\Sms\IppanelAgencyService;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TenantSmsProvisioningService
{
    public function __construct(
        protected IppanelAgencyService $agency,
    ) {}

    public function getOrCreateAccount(Tenant $tenant): TenantSmsAccount
    {
        return TenantSmsAccount::firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['status' => TenantSmsAccount::STATUS_DRAFT],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitRequest(Tenant $tenant, array $data, ?User $submitter = null): TenantSmsPanelRequest|TenantSmsAccount
    {
        $account = $this->getOrCreateAccount($tenant);

        if ($account->isActive()) {
            throw new RuntimeException('پنل پیامک این مجموعه قبلاً فعال شده است.');
        }

        if ($account->status === TenantSmsAccount::STATUS_PENDING) {
            throw new RuntimeException('درخواست شما در انتظار بررسی است.');
        }

        $request = TenantSmsPanelRequest::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'status' => TenantSmsPanelRequest::STATUS_PENDING,
                'name_family' => $data['name_family'],
                'company' => $data['company'] ?? null,
                'national_code' => $data['national_code'],
                'mobile_number' => $data['mobile_number'],
                'birth_date' => $data['birth_date'],
                'notes' => $data['notes'] ?? null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ],
        );

        $account->update(['status' => TenantSmsAccount::STATUS_PENDING]);

        if (config('sms.auto_approve_sms_requests')) {
            return $this->approve($tenant, $submitter ?? User::find($tenant->owner_id), config('sms.default_acl_id'));
        }

        return $request;
    }

    public function ensureDraftAccount(Tenant $tenant): TenantSmsAccount
    {
        return $this->getOrCreateAccount($tenant);
    }

    public function approve(Tenant $tenant, User $reviewer, ?int $aclId = null): TenantSmsAccount
    {
        $request = TenantSmsPanelRequest::where('tenant_id', $tenant->id)
            ->where('status', TenantSmsPanelRequest::STATUS_PENDING)
            ->firstOrFail();

        $account = $this->getOrCreateAccount($tenant);
        $username = $this->buildUsername($tenant);

        return DB::transaction(function () use ($tenant, $reviewer, $aclId, $request, $account, $username) {
            $created = $this->agency->createSubUser([
                'user_name' => $username,
                'national_code' => $request->national_code,
                'mobile_number' => $request->mobile_number,
                'birth_date' => $request->birth_date->format('Y-m-d'),
                'name_family' => $request->name_family,
                'company' => $request->company,
                'description' => "RahbarCrm tenant #{$tenant->id}",
                'acl_id' => $aclId ?? config('sms.default_acl_id'),
            ]);

            if (! $created['user_id']) {
                throw new RuntimeException('کاربر IPPanel ساخته شد ولی شناسه آن یافت نشد.');
            }

            $userInfo = $this->agency->showUser((int) $created['user_id']);
            $credit = (float) ($userInfo['credit']['credit'] ?? 0);

            $account->update([
                'status' => TenantSmsAccount::STATUS_ACTIVE,
                'ippanel_user_id' => $created['user_id'],
                'ippanel_username' => $created['username'],
                'password_encrypted' => Crypt::encryptString($created['password']),
                'acl_id' => $aclId ?? config('sms.default_acl_id'),
                'credit_cached' => $credit,
                'credit_synced_at' => now(),
                'activated_at' => now(),
            ]);

            $request->update([
                'status' => TenantSmsPanelRequest::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return $account->fresh();
        });
    }

    public function reject(Tenant $tenant, User $reviewer, string $reason): TenantSmsAccount
    {
        $request = TenantSmsPanelRequest::where('tenant_id', $tenant->id)
            ->where('status', TenantSmsPanelRequest::STATUS_PENDING)
            ->firstOrFail();

        $account = $this->getOrCreateAccount($tenant);

        $request->update([
            'status' => TenantSmsPanelRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $account->update(['status' => TenantSmsAccount::STATUS_REJECTED]);

        return $account->fresh();
    }

    public function syncCredit(TenantSmsAccount $account): TenantSmsAccount
    {
        if (! $account->ippanel_user_id) {
            return $account;
        }

        $credit = $this->agency->syncUserCredit((int) $account->ippanel_user_id);

        $account->update([
            'credit_cached' => $credit,
            'credit_synced_at' => now(),
        ]);

        return $account->fresh();
    }

    public function updateSettings(TenantSmsAccount $account, ?string $defaultFromNumber): TenantSmsAccount
    {
        if (! $account->isActive()) {
            throw new RuntimeException('پنل پیامک فعال نیست.');
        }

        $account->update([
            'default_from_number' => $defaultFromNumber,
        ]);

        return $account->fresh();
    }

    protected function buildUsername(Tenant $tenant): string
    {
        $base = 'rahbar_'.Str::slug($tenant->slug ?: $tenant->name, '_');

        return substr($base, 0, 20).'_'.$tenant->id;
    }
}
