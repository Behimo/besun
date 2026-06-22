<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Sms\SmsCreditPurchaseService;
use App\Application\Sms\SmsService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\SmsMessage;
use App\Infrastructure\Persistence\Eloquent\Models\SmsTemplate;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Persistence\Eloquent\Models\TenantSmsAccount;
use App\Infrastructure\Services\Sms\IppanelTenantClient;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected SmsService $sms,
        protected SmsCreditPurchaseService $creditPurchase,
        protected IppanelTenantClient $ippanel,
        protected TenantContext $tenantContext,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->requirePermission('sms.read');

        try {
            return response()->json($this->sms->dashboard());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'panel_active' => false], 422);
        }
    }

    public function numbers(Request $request): JsonResponse
    {
        $this->requirePermission('sms.read');

        $account = TenantSmsAccount::where('tenant_id', $this->tenantContext->tenantId())->firstOrFail();

        if (! $account->isActive()) {
            return response()->json(['numbers' => []]);
        }

        return response()->json([
            'numbers' => $this->ippanel->listNumbers($account),
            'default_from_number' => $account->default_from_number,
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        $this->requirePermission('sms.read');

        return response()->json([
            'templates' => SmsTemplate::orderBy('title')->get(),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $this->requirePermission('sms.manage');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'ippanel_pattern_code' => ['nullable', 'string', 'max:255'],
            'variables' => ['nullable', 'array'],
        ]);

        $template = $this->sms->createTemplate($data);

        return response()->json(['template' => $template], 201);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->requirePermission('sms.send');

        $filters = $request->validate([
            'audience' => ['nullable', 'in:leads,contacts,deals'],
            'pipeline_stage_ids' => ['nullable', 'array'],
            'pipeline_stage_ids.*' => ['integer'],
            'campaign_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'phones' => ['nullable', 'array'],
            'phones.*' => ['string'],
        ]);

        return response()->json($this->sms->previewAudience($this->crmUser(), $filters));
    }

    public function send(Request $request): JsonResponse
    {
        $this->requirePermission('sms.send');

        $payload = $request->validate([
            'message' => ['required_without:phone', 'nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string'],
            'from_number' => ['nullable', 'string'],
            'lead_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'audience' => ['nullable', 'in:leads,contacts,deals'],
            'pipeline_stage_ids' => ['nullable', 'array'],
            'pipeline_stage_ids.*' => ['integer'],
            'campaign_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'phones' => ['nullable', 'array'],
            'phones.*' => ['string'],
            'related_type' => ['nullable', 'string'],
            'related_id' => ['nullable', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        try {
            $message = $this->sms->send($this->crmUser(), $payload);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'پیامک در صف ارسال قرار گرفت.',
            'sms' => $message,
        ], 201);
    }

    public function messages(Request $request): JsonResponse
    {
        $this->requirePermission('sms.read');

        $messages = SmsMessage::with('sender:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($messages);
    }

    public function showMessage(SmsMessage $smsMessage): JsonResponse
    {
        $this->requirePermission('sms.read');

        return response()->json([
            'sms' => $smsMessage->load(['recipients', 'sender:id,name']),
        ]);
    }

    public function creditPackages(): JsonResponse
    {
        $this->requirePermission('sms.credit');

        try {
            return response()->json(['packages' => $this->creditPurchase->listPackages()]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function purchaseCredit(Request $request): JsonResponse
    {
        $this->requirePermission('sms.credit');

        $tenant = Tenant::findOrFail($this->tenantContext->tenantId());

        if (! $tenant->isOwner($request->user())) {
            abort(403, 'فقط مالک مجموعه می‌تواند شارژ بخرد.');
        }

        $data = $request->validate([
            'package_id' => ['required', 'integer'],
        ]);

        try {
            $order = $this->creditPurchase->purchase($tenant, $request->user(), $data['package_id']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'شارژ با موفقیت انجام شد.',
            'order' => $order,
        ]);
    }
}
