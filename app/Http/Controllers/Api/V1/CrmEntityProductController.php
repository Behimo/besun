<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Product\CrmEntityProductService;
use App\Http\Controllers\Concerns\ChecksCrmAccess;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Deal;
use App\Infrastructure\Persistence\Eloquent\Models\Lead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmEntityProductController extends Controller
{
    use ChecksCrmAccess;

    public function __construct(
        protected CrmEntityProductService $entityProducts,
    ) {}

    public function indexLead(Lead $lead): JsonResponse
    {
        $this->requirePermission('leads.read');
        $this->assertCanViewRecord($lead);

        return $this->productResponse($lead);
    }

    public function syncLead(Request $request, Lead $lead): JsonResponse
    {
        $this->requirePermission('leads.update');
        $this->assertCanViewRecord($lead);

        $items = $request->validate([
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.quantity' => ['nullable', 'integer', 'min:1'],
            'products.*.notes' => ['nullable', 'string', 'max:500'],
            'products.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->entityProducts->sync($lead, $items['products']);

        return $this->productResponse($lead->fresh());
    }

    public function attachLead(Request $request, Lead $lead): JsonResponse
    {
        $this->requirePermission('leads.update');
        $this->assertCanViewRecord($lead);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->entityProducts->attach($lead, (int) $data['product_id'], $data);

        return $this->productResponse($lead->fresh());
    }

    public function detachLead(Lead $lead, int $productId): JsonResponse
    {
        $this->requirePermission('leads.update');
        $this->assertCanViewRecord($lead);

        $this->entityProducts->detach($lead, $productId);

        return $this->productResponse($lead->fresh());
    }

    public function indexDeal(Deal $deal): JsonResponse
    {
        $this->requirePermission('deals.read');
        $this->assertCanViewRecord($deal);

        return $this->productResponse($deal);
    }

    public function syncDeal(Request $request, Deal $deal): JsonResponse
    {
        $this->requirePermission('deals.update');
        $this->assertCanViewRecord($deal);

        $items = $request->validate([
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.quantity' => ['nullable', 'integer', 'min:1'],
            'products.*.notes' => ['nullable', 'string', 'max:500'],
            'products.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->entityProducts->sync($deal, $items['products']);

        return $this->productResponse($deal->fresh());
    }

    public function attachDeal(Request $request, Deal $deal): JsonResponse
    {
        $this->requirePermission('deals.update');
        $this->assertCanViewRecord($deal);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->entityProducts->attach($deal, (int) $data['product_id'], $data);

        return $this->productResponse($deal->fresh());
    }

    public function detachDeal(Deal $deal, int $productId): JsonResponse
    {
        $this->requirePermission('deals.update');
        $this->assertCanViewRecord($deal);

        $this->entityProducts->detach($deal, $productId);

        return $this->productResponse($deal->fresh());
    }

    protected function productResponse(Model $entity): JsonResponse
    {
        $entity->load(['products' => fn ($q) => $q->select('products.id', 'products.name', 'products.sku', 'products.image_url', 'products.price', 'products.currency')]);

        return response()->json([
            'products' => $entity->products->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'image_url' => $p->image_url,
                'price' => $p->price,
                'currency' => $p->currency,
                'quantity' => $p->pivot->quantity,
                'notes' => $p->pivot->notes,
                'sort_order' => $p->pivot->sort_order,
            ]),
        ]);
    }
}
