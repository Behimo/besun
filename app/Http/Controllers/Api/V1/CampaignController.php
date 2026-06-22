<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    use ChecksCrmAccess;

    public function index(Request $request): JsonResponse
    {
        $this->requirePermission('campaigns.read');

        $campaigns = Campaign::query()
            ->withCount('leads')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return response()->json($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission('campaigns.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,active,paused,completed'],
            'channel' => ['nullable', 'string', 'max:100'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $campaign = Campaign::create($data);

        return response()->json(['campaign' => $campaign->loadCount('leads')], 201);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        $this->requirePermission('campaigns.read');

        return response()->json(['campaign' => $campaign->loadCount('leads')]);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $this->requirePermission('campaigns.update');

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,active,paused,completed'],
            'channel' => ['nullable', 'string', 'max:100'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $campaign->update($data);

        return response()->json(['campaign' => $campaign->fresh()->loadCount('leads')]);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $this->requirePermission('campaigns.delete');
        $campaign->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
