<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Crm\CrmHandoffService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\CrmHandoff;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmHandoffController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected CrmHandoffService $handoffs,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $contactId = $request->filled('contact_id') ? $request->integer('contact_id') : null;

        return response()->json([
            'handoffs' => $this->handoffs->pendingForUser($request->user(), $contactId),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'in:deal,lead'],
            'entity_id' => ['required', 'integer', 'min:1'],
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'to_stage_id' => ['nullable', 'integer', 'exists:pipeline_stages,id'],
            'handoff_type' => ['nullable', 'in:assign,finance,return'],
            'note' => ['nullable', 'string', 'max:2000'],
            'create_task' => ['nullable', 'boolean'],
            'task_title' => ['nullable', 'string', 'max:255'],
            'task_due_at' => ['nullable', 'date'],
        ]);

        $this->assertCanAssignEntity($data['entity_type'], (int) $data['entity_id']);

        $handoff = $this->handoffs->assign(
            $request->user(),
            $data['entity_type'],
            (int) $data['entity_id'],
            $data,
        );

        return response()->json([
            'handoff' => $this->handoffs->formatHandoff($handoff),
            'message' => 'واگذاری با موفقیت انجام شد.',
        ], 201);
    }

    protected function assertCanAssignEntity(string $entityType, int $entityId): void
    {
        if ($entityType === 'lead') {
            $this->requirePermission('leads.assign');
            $this->assertCanViewRecord(Lead::findOrFail($entityId));

            return;
        }

        $this->requirePermission('deals.update');
        $this->assertCanViewRecord(Deal::findOrFail($entityId));
    }

    public function complete(Request $request, CrmHandoff $handoff): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $handoff = $this->handoffs->complete($request->user(), $handoff, $data['note'] ?? null);

        return response()->json([
            'handoff' => $this->handoffs->formatHandoff($handoff),
            'message' => 'واگذاری تکمیل شد.',
        ]);
    }

    public function returnToSender(Request $request, CrmHandoff $handoff): JsonResponse
    {
        $data = $request->validate([
            'return_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'to_stage_id' => ['nullable', 'integer', 'exists:pipeline_stages,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $returnHandoff = $this->handoffs->returnToSender($request->user(), $handoff, $data);

        return response()->json([
            'handoff' => $this->handoffs->formatHandoff($returnHandoff),
            'message' => 'پرونده به فروش بازگردانده شد.',
        ]);
    }
}
