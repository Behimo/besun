<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Automation\AutomationDispatcher;
use App\Application\Pipeline\PipelineTransitionLogger;
use App\Domain\Shared\Enums\Department;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Support\FollowUpReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    use ChecksCrmAccess;
    use FollowUpReminder;

    public function __construct(
        protected PipelineTransitionLogger $transitions,
        protected AutomationDispatcher $automation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('deals.read');

        if ($request->boolean('kanban')) {
            $user = $this->crmUser();
            $tenantId = $this->crmTenantId();

            $stages = PipelineStage::query()
                ->where('type', 'sales')
                ->with(['deals' => function ($q) use ($user, $tenantId) {
                    $q->with([
                        'contact:id,name',
                        'assignee:id,name',
                        'products' => fn ($pq) => $pq->select('products.id', 'products.name', 'products.sku', 'products.image_url'),
                    ])
                    ->withCount(['quotes as active_quotes_count' => fn ($q) => $q->whereIn('status', ['draft', 'sent'])])
                    ->orderByDesc('updated_at');
                    app(\App\Support\DepartmentAccessService::class)
                        ->scopeDepartmentRecords($q, $user, $tenantId);
                }])
                ->orderBy('sort_order')
                ->get();

            return response()->json(['stages' => $stages]);
        }

        $deals = $this->scopeByDepartment(Deal::with(['stage', 'contact']))->latest()->paginate(15);

        return response()->json($deals);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('deals.create');

        $data = $request->validate([
            'pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
            'follow_up_reminder_at' => ['nullable', 'date'],
        ]);

        $data = $this->applyFollowUpReminders($data);
        $data['department'] = Department::Sales->value;

        $deal = Deal::create($data);

        $this->transitions->log('deal', $deal->id, null, $deal->pipeline_stage_id);

        $this->automation->dispatch('deal.created', $deal, ['actor_id' => $request->user()->id]);

        return response()->json(['deal' => $deal->load(['stage', 'contact'])], 201);
    }

    public function show(Deal $deal): JsonResponse
    {
        $this->requirePermission('deals.read');
        $this->assertCanViewRecord($deal);

        return response()->json([
            'deal' => $deal->load([
                'stage',
                'contact',
                'products' => fn ($q) => $q->select('products.id', 'products.name', 'products.sku', 'products.image_url', 'products.price'),
            ]),
        ]);
    }

    public function update(Request $request, Deal $deal): JsonResponse
    {
        $this->requirePermission('deals.update');
        $this->assertCanViewRecord($deal);

        $data = $request->validate([
            'pipeline_stage_id' => ['sometimes', 'exists:pipeline_stages,id'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
            'follow_up_reminder_at' => ['nullable', 'date'],
        ]);

        $data = $this->applyFollowUpReminders($data, $deal);

        $fromStageId = $deal->pipeline_stage_id;
        $deal->update($data);

        if (isset($data['pipeline_stage_id']) && $data['pipeline_stage_id'] !== $fromStageId) {
            $this->transitions->log('deal', $deal->id, $fromStageId, $data['pipeline_stage_id']);
            $this->automation->dispatch('deal.stage_changed', $deal->fresh(), [
                'actor_id' => $request->user()->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $data['pipeline_stage_id'],
            ]);
        }

        return response()->json([
            'deal' => $deal->load([
                'stage',
                'contact',
                'products' => fn ($q) => $q->select('products.id', 'products.name', 'products.sku', 'products.image_url', 'products.price'),
            ]),
        ]);
    }

    public function updateStage(Request $request, Deal $deal): JsonResponse
    {
        $this->requirePermission('deals.update');
        $this->assertCanViewRecord($deal);

        $data = $request->validate([
            'pipeline_stage_id' => ['required', 'exists:pipeline_stages,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $fromStageId = $deal->pipeline_stage_id;
        $deal->update($data);

        if ($data['pipeline_stage_id'] !== $fromStageId) {
            $this->transitions->log('deal', $deal->id, $fromStageId, $data['pipeline_stage_id']);
            $this->automation->dispatch('deal.stage_changed', $deal->fresh(), [
                'actor_id' => $request->user()->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $data['pipeline_stage_id'],
            ]);
        }

        return response()->json(['deal' => $deal->load(['stage', 'contact', 'assignee:id,name'])]);
    }

    public function destroy(Deal $deal): JsonResponse
    {
        $this->requirePermission('deals.delete');
        $this->assertCanViewRecord($deal);
        $deal->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
