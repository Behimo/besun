<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\SubscriptionTransaction;
use App\Infrastructure\Persistence\Eloquent\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownedTenantIds = Tenant::where('owner_id', $user->id)->pluck('id');

        $perPage = min((int) $request->query('per_page', 10), 50);
        $page = max((int) $request->query('page', 1), 1);

        $query = SubscriptionTransaction::query()
            ->whereIn('tenant_id', $ownedTenantIds)
            ->with('tenant:id,name,slug')
            ->orderByDesc('created_at');

        if ($search = $request->query('q')) {
            $query->whereHas('tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $transactions = collect($paginator->items())->map(fn (SubscriptionTransaction $tx) => [
            'id' => $tx->id,
            'tenant_id' => $tx->tenant_id,
            'tenant_name' => $tx->tenant?->name,
            'amount' => (float) $tx->amount,
            'status' => $tx->status,
            'gateway_reference' => $tx->gateway_reference,
            'items' => $tx->items,
            'summary' => $this->summarizeItems($tx->items),
            'paid_at' => $tx->paid_at,
            'created_at' => $tx->created_at,
        ]);

        return response()->json([
            'transactions' => $transactions,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
        ]);
    }

    protected function summarizeItems(?array $items): string
    {
        if (! $items) {
            return '';
        }

        $parts = [];

        if (! empty($items['seat_count'])) {
            $parts[] = $items['seat_count'].' کارمند';
        }

        $modules = $items['modules'] ?? [];
        foreach ($modules as $module) {
            $parts[] = $module['title'] ?? 'ماژول';
        }

        return implode(' · ', $parts);
    }
}
