<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Sms\TenantSmsProvisioningService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsAccount;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsPanelRequest;
use App\Infrastructure\Services\TenantContext;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantSmsSettingsController extends Controller
{
    public function __construct(
        protected TenantSmsProvisioningService $provisioning,
        protected TenantContext $tenantContext,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $tenant = $this->ownerTenant($request);
        $account = $this->provisioning->getOrCreateAccount($tenant);
        $panelRequest = TenantSmsPanelRequest::where('tenant_id', $tenant->id)->first();

        return response()->json([
            'account' => $this->formatAccount($account),
            'request' => $panelRequest ? $this->formatRequest($panelRequest, false) : null,
            'has_sms_module' => $tenant->hasModule('mod-sms'),
        ]);
    }

    public function submitRequest(Request $request): JsonResponse
    {
        $tenant = $this->ownerTenant($request);

        if (! $tenant->hasModule('mod-sms')) {
            abort(402, 'ماژول پیامک برای این مجموعه فعال نیست.');
        }

        $data = $request->validate([
            'name_family' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'national_code' => ['required', 'string', 'size:10'],
            'mobile_number' => ['required', 'string'],
            'birth_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $mobile = PhoneNormalizer::normalize($data['mobile_number']);
        if (! $mobile) {
            return response()->json(['message' => 'شماره موبایل معتبر نیست.'], 422);
        }

        $data['mobile_number'] = $mobile;

        try {
            $result = $this->provisioning->submitRequest($tenant, $data, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($result instanceof TenantSmsAccount) {
            return response()->json([
                'message' => 'پنل پیامک مجموعه فعال شد.',
                'account' => $this->formatAccount($result),
                'request' => null,
            ]);
        }

        return response()->json([
            'message' => 'درخواست پنل پیامک ثبت شد و در انتظار تأیید است.',
            'request' => $this->formatRequest($result, false),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $tenant = $this->ownerTenant($request);
        $account = TenantSmsAccount::where('tenant_id', $tenant->id)->firstOrFail();

        $data = $request->validate([
            'default_from_number' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $account = $this->provisioning->updateSettings($account, $data['default_from_number'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تنظیمات پیامک ذخیره شد.',
            'account' => $this->formatAccount($account),
        ]);
    }

    protected function ownerTenant(Request $request): Tenant
    {
        $tenant = Tenant::findOrFail($this->tenantContext->tenantId());

        if (! $tenant->isOwner($request->user())) {
            abort(403, 'فقط مالک مجموعه به تنظیمات پیامک دسترسی دارد.');
        }

        return $tenant;
    }

    protected function formatAccount(TenantSmsAccount $account): array
    {
        return [
            'status' => $account->status,
            'ippanel_username' => $account->ippanel_username,
            'default_from_number' => $account->default_from_number,
            'credit' => $account->credit_cached !== null ? (float) $account->credit_cached : null,
            'credit_synced_at' => $account->credit_synced_at,
            'activated_at' => $account->activated_at,
            'is_active' => $account->isActive(),
        ];
    }

    protected function formatRequest(TenantSmsPanelRequest $panelRequest, bool $includeSensitive): array
    {
        $data = [
            'status' => $panelRequest->status,
            'name_family' => $panelRequest->name_family,
            'company' => $panelRequest->company,
            'mobile_number' => PhoneNormalizer::display($panelRequest->mobile_number),
            'birth_date' => $panelRequest->birth_date?->format('Y-m-d'),
            'notes' => $panelRequest->notes,
            'rejection_reason' => $panelRequest->rejection_reason,
            'reviewed_at' => $panelRequest->reviewed_at,
            'created_at' => $panelRequest->created_at,
        ];

        if ($includeSensitive) {
            $data['national_code'] = $panelRequest->national_code;
        }

        return $data;
    }
}
