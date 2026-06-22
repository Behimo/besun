<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Automation\AutomationDispatcher;
use App\Application\Contact\ContactResolver;
use App\Application\Lead\ConvertLeadUseCase;
use App\Application\Lead\LeadResolver;
use App\Application\Pipeline\PipelineTransitionLogger;
use App\Domain\Shared\Enums\Department;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Support\DepartmentAccessService;
use App\Support\FollowUpReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    use ChecksCrmAccess;
    use FollowUpReminder;

    public function __construct(
        protected ConvertLeadUseCase $convertLead,
        protected PipelineTransitionLogger $transitions,
        protected ContactResolver $contactResolver,
        protected LeadResolver $leadResolver,
        protected AutomationDispatcher $automation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('leads.read');

        if ($request->boolean('kanban')) {
            $user = $this->crmUser();
            $tenantId = $this->crmTenantId();
            $access = app(DepartmentAccessService::class);

            $stages = PipelineStage::query()
                ->where('type', 'marketing')
                ->with(['leads' => function ($q) use ($user, $tenantId, $access) {
                    $q->with([
                        'campaign',
                        'assignee:id,name',
                        'contact:id,name',
                        'products' => fn ($pq) => $pq->select('products.id', 'products.name', 'products.sku', 'products.image_url'),
                    ])
                        ->where('status', '!=', 'converted')
                        ->orderByDesc('updated_at');
                    $access->scopeDepartmentRecords($q, $user, $tenantId);
                }])
                ->withCount(['leads as leads_count' => function ($q) use ($user, $tenantId, $access) {
                    $q->where('status', '!=', 'converted');
                    $access->scopeDepartmentRecords($q, $user, $tenantId);
                }])
                ->orderBy('sort_order')
                ->get();

            $readyForSalesStageId = $stages
                ->where('is_lost', false)
                ->sortByDesc('sort_order')
                ->first()
                ?->id;

            $stages = $stages->map(function ($stage) use ($readyForSalesStageId) {
                $stage->setAttribute('is_ready_for_sales', $stage->id === $readyForSalesStageId);

                return $stage;
            });

            return response()->json(['stages' => $stages]);
        }

        $leads = $this->scopeByDepartment(Lead::query())
            ->with([
                'campaign',
                'marketingStage',
                'assignee:id,name',
                'contact:id,name',
                'products' => fn ($q) => $q->select('products.id', 'products.name', 'products.sku', 'products.image_url'),
            ])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->campaign_id, fn ($q, $id) => $q->where('campaign_id', $id))
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 100));

        return response()->json($leads);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('leads.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'marketing_stage_id' => ['nullable', 'exists:pipeline_stages,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
            'follow_up_reminder_at' => ['nullable', 'date'],
        ]);

        $data = $this->applyFollowUpReminders($data);

        if (empty($data['marketing_stage_id'])) {
            $data['marketing_stage_id'] = PipelineStage::query()
                ->where('type', 'marketing')
                ->orderBy('sort_order')
                ->value('id');
        }

        $data['department'] = Department::Marketing->value;
        $data['assigned_to'] = $data['assigned_to'] ?? $request->user()->id;

        $result = $this->leadResolver->findOrCreateFromData($data);
        $lead = $result['lead'];

        if ($result['created'] && $lead->marketing_stage_id) {
            $this->transitions->log('lead', $lead->id, null, $lead->marketing_stage_id);
        }

        if ($result['created']) {
            $this->automation->dispatch('lead.created', $lead, ['actor_id' => $request->user()->id]);
        }

        return response()->json(['lead' => $lead->load(['campaign', 'marketingStage', 'assignee:id,name', 'contact:id,name'])], 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        $this->requirePermission('leads.read');
        $this->assertCanViewRecord($lead);

        if (! $lead->contact_id) {
            $this->contactResolver->syncLeadToContact($lead);
            $lead->refresh();
        }

        return response()->json([
            'lead' => $lead->load(['campaign', 'marketingStage', 'assignee:id,name', 'contact:id,name']),
            'profile_url' => $lead->contact_id ? '/apps/crm/contacts/'.$lead->contact_id : null,
        ]);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $this->requirePermission('leads.update');
        $this->assertCanViewRecord($lead);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'marketing_stage_id' => ['nullable', 'exists:pipeline_stages,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
            'follow_up_reminder_at' => ['nullable', 'date'],
        ]);

        $data = $this->applyFollowUpReminders($data, $lead);

        $fromStageId = $lead->marketing_stage_id;
        $lead->update($data);

        $this->contactResolver->syncLeadToContact($lead->fresh());

        if (isset($data['marketing_stage_id']) && $data['marketing_stage_id'] !== $fromStageId) {
            $this->transitions->log('lead', $lead->id, $fromStageId, $data['marketing_stage_id']);
            $this->automation->dispatch('lead.stage_changed', $lead->fresh(), [
                'actor_id' => $request->user()->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $data['marketing_stage_id'],
            ]);
        }

        return response()->json(['lead' => $lead->load(['campaign', 'marketingStage', 'assignee:id,name'])]);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $this->requirePermission('leads.delete');
        $this->assertCanViewRecord($lead);
        $lead->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function updateStage(Request $request, Lead $lead): JsonResponse
    {
        $this->requirePermission('leads.update');
        $this->assertCanViewRecord($lead);

        $data = $request->validate([
            'marketing_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $fromStageId = $lead->marketing_stage_id;
        $lead->update($data);

        if ($data['marketing_stage_id'] !== $fromStageId) {
            $this->transitions->log('lead', $lead->id, $fromStageId, $data['marketing_stage_id']);
            $this->automation->dispatch('lead.stage_changed', $lead->fresh(), [
                'actor_id' => $request->user()->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $data['marketing_stage_id'],
            ]);
        }

        return response()->json(['lead' => $lead->load(['campaign', 'marketingStage', 'assignee:id,name', 'contact:id,name'])]);
    }

    public function convert(Request $request, Lead $lead): JsonResponse
    {
        $this->requirePermission('leads.update');
        $this->assertCanViewRecord($lead);

        $data = $request->validate([
            'pipeline_stage_id' => ['nullable', 'exists:pipeline_stages,id'],
            'deal_title' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->convertLead->execute(
            $lead,
            $data['pipeline_stage_id'] ?? null,
            $data['deal_title'] ?? null,
            isset($data['amount']) ? (float) $data['amount'] : null,
            $request->user()->id,
        );

        return response()->json([
            'contact' => [
                'id' => $result['contact']->id,
                'name' => $result['contact']->name,
            ],
            'deal' => $result['deal'],
            'lead' => $result['lead'],
            'message' => 'لید با موفقیت تبدیل شد و در قیف فروش ثبت گردید.',
        ]);
    }
}
