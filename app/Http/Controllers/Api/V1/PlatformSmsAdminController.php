<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Platform\PlatformAuditService;
use App\Application\Sms\TenantSmsProvisioningService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsAccount;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsPanelRequest;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSmsAdminController extends Controller
{
    public function __construct(
        protected TenantSmsProvisioningService $provisioning,
        protected PlatformAuditService $audit,
    ) {}

    public function requests(): JsonResponse
    {
        $requests = TenantSmsPanelRequest::with(['tenant:id,name,slug'])
            ->where('status', TenantSmsPanelRequest::STATUS_PENDING)
            ->latest()
            ->get()
            ->map(fn ($req) => [
                'tenant' => [
                    'id' => $req->tenant->id,
                    'name' => $req->tenant->name,
                    'slug' => $req->tenant->slug,
                ],
                'request' => [
                    'status' => $req->status,
                    'name_family' => $req->name_family,
                    'company' => $req->company,
                    'national_code' => $req->national_code,
                    'mobile_number' => PhoneNormalizer::display($req->mobile_number),
                    'birth_date' => $req->birth_date?->format('Y-m-d'),
                    'notes' => $req->notes,
                    'created_at' => $req->created_at,
                ],
            ]);

        return response()->json(['requests' => $requests]);
    }

    public function showTenant(Tenant $tenant): JsonResponse
    {
        $account = TenantSmsAccount::where('tenant_id', $tenant->id)->first();
        $panelRequest = TenantSmsPanelRequest::where('tenant_id', $tenant->id)->first();

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'account' => $account ? [
                'status' => $account->status,
                'ippanel_user_id' => $account->ippanel_user_id,
                'ippanel_username' => $account->ippanel_username,
                'credit' => $account->credit_cached !== null ? (float) $account->credit_cached : null,
                'credit_synced_at' => $account->credit_synced_at,
                'activated_at' => $account->activated_at,
                'is_active' => $account->isActive(),
            ] : null,
            'request' => $panelRequest ? [
                'status' => $panelRequest->status,
                'name_family' => $panelRequest->name_family,
                'company' => $panelRequest->company,
                'national_code' => $panelRequest->national_code,
                'mobile_number' => PhoneNormalizer::display($panelRequest->mobile_number),
                'birth_date' => $panelRequest->birth_date?->format('Y-m-d'),
                'notes' => $panelRequest->notes,
                'rejection_reason' => $panelRequest->rejection_reason,
            ] : null,
        ]);
    }

    public function approve(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'acl_id' => ['nullable', 'integer'],
        ]);

        try {
            $account = $this->provisioning->approve($tenant, $request->user(), $data['acl_id'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->audit->log($request->user(), 'sms.approved', 'tenant', $tenant->id);

        return response()->json([
            'message' => 'پنل پیامک مجموعه فعال شد.',
            'account' => [
                'status' => $account->status,
                'ippanel_username' => $account->ippanel_username,
                'credit' => (float) $account->credit_cached,
            ],
        ]);
    }

    public function reject(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $account = $this->provisioning->reject($tenant, $request->user(), $data['reason']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->audit->log($request->user(), 'sms.rejected', 'tenant', $tenant->id, [
            'reason' => $data['reason'],
        ]);

        return response()->json([
            'message' => 'درخواست رد شد.',
            'account' => ['status' => $account->status],
        ]);
    }

    public function syncCredit(Tenant $tenant): JsonResponse
    {
        $account = TenantSmsAccount::where('tenant_id', $tenant->id)->firstOrFail();

        try {
            $account = $this->provisioning->syncCredit($account);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'credit' => (float) $account->credit_cached,
            'credit_synced_at' => $account->credit_synced_at,
        ]);
    }
}
