<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\PipelineStage;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use App\Infrastructure\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineStageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type', 'sales');

        $stages = PipelineStage::query()
            ->where('type', $type)
            ->withCount('deals')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['stages' => $stages]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertOwner($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'type' => ['nullable', 'string', 'in:sales,marketing'],
        ]);

        $type = $data['type'] ?? 'sales';
        $maxOrder = PipelineStage::query()->where('type', $type)->max('sort_order') ?? 0;

        $stage = PipelineStage::create([
            'name' => $data['name'],
            'color' => $data['color'] ?? '#7367F0',
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
            'type' => $type,
        ]);

        return response()->json(['stage' => $stage->loadCount('deals')], 201);
    }

    public function update(Request $request, PipelineStage $pipelineStage): JsonResponse
    {
        $this->assertOwner($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $pipelineStage->update($data);

        return response()->json(['stage' => $pipelineStage->fresh()->loadCount('deals')]);
    }

    public function destroy(Request $request, PipelineStage $pipelineStage): JsonResponse
    {
        $this->assertOwner($request);

        $minStages = config('crm_pipeline.min_stages', 2);
        $total = PipelineStage::query()->where('type', $pipelineStage->type)->count();

        if ($total <= $minStages) {
            return response()->json([
                'message' => "حداقل {$minStages} مرحله در این قیف باید باقی بماند.",
            ], 422);
        }

        if ($pipelineStage->deals()->exists() || $pipelineStage->leads()->exists()) {
            return response()->json([
                'message' => 'این مرحله دارای معامله است و قابل حذف نیست.',
            ], 422);
        }

        $pipelineStage->delete();

        return response()->json(['message' => 'مرحله حذف شد.']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->assertOwner($request);

        $data = $request->validate([
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.id' => ['required', 'integer', 'exists:pipeline_stages,id'],
            'stages.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['stages'] as $item) {
            PipelineStage::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
            ]);
        }

        $stages = PipelineStage::query()
            ->withCount('deals')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['stages' => $stages]);
    }

    protected function assertOwner(Request $request): void
    {
        $tenantId = app(TenantContext::class)->tenantId();
        $tenant = Tenant::findOrFail($tenantId);

        if (! $tenant->isOwner($request->user())) {
            abort(403, 'فقط مالک مجموعه می‌تواند قیف فروش را مدیریت کند.');
        }
    }
}
